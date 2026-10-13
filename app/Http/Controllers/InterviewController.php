<?php

namespace App\Http\Controllers;

use App\Enums\CandidateStatus;
use App\Mail\InterviewScheduledMail;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\Interview;
use App\Models\InterviewSlot;
use App\Services\InterviewInvitationService;
use App\Services\RealtimePublisher;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InterviewController extends Controller
{
    public function __construct(private readonly InterviewInvitationService $invitationService)
    {
    }

    public function index(): Response
    {
        $timezone = 'America/Bogota';
        $slots = InterviewSlot::query()->with('interview.candidate.lead')
            ->where('starts_at', '>=', now('UTC'))->orderBy('starts_at')->limit(250)->get();
        $slots = $slots->map(function (InterviewSlot $slot) use ($timezone) {
            $startsAt = Carbon::createFromFormat('Y-m-d H:i:s', $slot->getRawOriginal('starts_at'), 'UTC')->setTimezone($timezone);
            $endsAt = Carbon::createFromFormat('Y-m-d H:i:s', $slot->getRawOriginal('ends_at'), 'UTC')->setTimezone($timezone);

            return array_merge($slot->toArray(), [
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ]);
        });
        $candidates = Candidate::query()->with('lead')->whereIn('status', [CandidateStatus::PREQUALIFIED, CandidateStatus::INTERVIEW])
            ->whereHas('lead')->whereDoesntHave('interviews', fn ($query) => $query->whereIn('status', ['INVITED', 'SCHEDULED']))->latest()->get();
        $interviews = Interview::query()->with('candidate.lead', 'slot')->whereIn('status', ['INVITED', 'SCHEDULED'])
            ->latest()->limit(100)->get();

        return Inertia::render('Admin/Recruitment/Interviews/Index', compact('slots', 'candidates', 'interviews'));
    }

    public function generateSlots(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $timezone = 'America/Bogota';
        $validated['weekdays'] = array_map('intval', $validated['weekdays']);
        $from = Carbon::parse($validated['from_date'], $timezone)->startOfDay();
        $to = Carbon::parse($validated['to_date'], $timezone)->startOfDay();
        if ($from->diffInDays($to) > 90) {
            return back()->withErrors(['to_date' => 'El rango máximo para generar horarios es de 90 días.']);
        }
        $created = 0;
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            if (in_array($cursor->dayOfWeekIso, $validated['weekdays'], true)) {
                $start = Carbon::createFromFormat('Y-m-d H:i', $cursor->format('Y-m-d') . ' ' . $validated['start_time'], $timezone);
                $end = Carbon::createFromFormat('Y-m-d H:i', $cursor->format('Y-m-d') . ' ' . $validated['end_time'], $timezone);
                for ($slotStart = $start->copy(); $slotStart->lt($end); $slotStart->addHour()) {
                    $slotEnd = $slotStart->copy()->addHour();
                    if ($slotStart->isFuture() && ! InterviewSlot::where('starts_at', $slotStart->utc())->where('ends_at', $slotEnd->utc())->exists()) {
                        InterviewSlot::create(['starts_at' => $slotStart->utc(), 'ends_at' => $slotEnd->utc(), 'created_by' => $request->user()->id]);
                        $created++;
                    }
                }
            }
            $cursor->addDay();
        }

        if ($created > 0) {
            app(RealtimePublisher::class)->publish('interview.slots_generated', [
                'notification' => ['title' => 'Horarios disponibles', 'description' => "Se generaron {$created} franjas de entrevista."],
            ]);
        }

        return back()->with('success', $created ? "Se generaron {$created} franjas de entrevista de una hora." : 'No había franjas nuevas para generar.');
    }

    public function updateSlot(Request $request, InterviewSlot $slot): RedirectResponse
    {
        abort_if($slot->status !== 'AVAILABLE' || $slot->interview()->exists(), 422, 'No se puede editar una franja reservada.');

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);
        $timezone = 'America/Bogota';
        $start = Carbon::createFromFormat('Y-m-d H:i', "{$validated['date']} {$validated['start_time']}", $timezone);
        abort_if($start->isPast(), 422, 'La franja debe estar en el futuro.');
        $end = $start->copy()->addHour();

        $duplicate = InterviewSlot::query()
            ->where('id', '<>', $slot->id)
            ->where('starts_at', $start->utc())
            ->where('ends_at', $end->utc())
            ->exists();
        abort_if($duplicate, 422, 'Ya existe una franja con ese horario.');

        $slot->update(['starts_at' => $start->utc(), 'ends_at' => $end->utc()]);

        return back()->with('success', 'Franja actualizada correctamente.');
    }

    public function destroyAvailableSlots(): RedirectResponse
    {
        $deleted = InterviewSlot::query()
            ->where('status', 'AVAILABLE')
            ->whereDoesntHave('interview')
            ->delete();

        return back()->with('success', $deleted
            ? "Se eliminaron {$deleted} franjas disponibles."
            : 'No había franjas disponibles para eliminar.');
    }

    public function destroySlot(InterviewSlot $slot): RedirectResponse
    {
        abort_if($slot->status !== 'AVAILABLE' || $slot->interview()->exists(), 422, 'No se puede eliminar una franja reservada.');

        $slot->delete();

        return back()->with('success', 'Franja eliminada correctamente.');
    }

    public function invite(Request $request, Candidate $candidate): RedirectResponse
    {
        $candidate->loadMissing('lead');
        abort_unless(in_array($candidate->status, [CandidateStatus::PREQUALIFIED, CandidateStatus::INTERVIEW], true), 422, 'La candidata debe estar precalificada antes de agendar una entrevista.');
        $interview = null;
        try {
            $interview = $this->invitationService->send($candidate, $request->user());
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['interview' => 'No se pudo enviar la invitación. Revisa la configuración de correo.']);
        }
        if (! $interview) {
            return back()->withErrors(['interview' => 'Genera al menos una franja disponible antes de enviar la invitación.']);
        }
        $activity = $candidate->activities()->latest('id')->first();
        app(RealtimePublisher::class)->publishLead($candidate->fresh('lead')->lead, 'interview.invited', ['interview_id' => $interview->id, 'activity' => $activity?->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Invitación de entrevista enviada', 'description' => "Se enviaron horarios a {$candidate->lead->full_name}."]]);
        return back()->with('success', 'Horarios de entrevista enviados al correo registrado.');
    }

    public function updateStatus(Request $request, Interview $interview): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['SCHEDULED', 'COMPLETED', 'NO_SHOW', 'CANCELLED'])]]);
        $interview->update(['status' => $validated['status']]);
        if ($validated['status'] === 'CANCELLED' && $interview->slot) $interview->slot->update(['status' => 'AVAILABLE']);
        $interview->load('candidate.lead');
        $activity = CandidateActivity::create(['candidate_id' => $interview->candidate_id, 'user_id' => $request->user()->id, 'type' => 'interview_status_changed', 'description' => "Estado de entrevista actualizado a {$validated['status']}.", 'metadata' => ['interview_id' => $interview->id, 'status' => $validated['status']]]);
        app(RealtimePublisher::class)->publishLead($interview->candidate->lead, 'interview.status_changed', ['interview_id' => $interview->id, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Entrevista actualizada', 'description' => "La entrevista de {$interview->candidate->lead->full_name} ahora está {$validated['status']}."]]);
        return back()->with('success', 'Estado de la entrevista actualizado.');
    }

    public function reschedule(Request $request, Interview $interview): RedirectResponse
    {
        abort_unless($interview->status === 'SCHEDULED', 422, 'Solo se pueden reprogramar entrevistas agendadas.');
        $interview->loadMissing('candidate.lead', 'slot');

        $hasAlternative = InterviewSlot::where('status', 'AVAILABLE')->where('starts_at', '>', now())->exists()
            || ($interview->slot && $interview->slot->starts_at->isFuture());
        if (! $hasAlternative) {
            return back()->withErrors(['interview' => 'Genera una nueva franja disponible antes de reprogramar.']);
        }

        if ($interview->slot) {
            $interview->slot->update(['status' => 'AVAILABLE']);
        }
        $interview->update(['status' => 'CANCELLED']);
        CandidateActivity::create([
            'candidate_id' => $interview->candidate_id,
            'user_id' => $request->user()->id,
            'type' => 'interview_rescheduled',
            'description' => 'La entrevista fue liberada para que la candidata elija un nuevo horario.',
            'metadata' => ['previous_scheduled_at' => $interview->scheduled_at?->toIso8601String()],
        ]);

        return $this->invite($request, $interview->candidate);
    }

    public function booking(Interview $interview, string $token): Response
    {
        $this->validateInvitation($interview, $token);
        $slots = InterviewSlot::where('status', 'AVAILABLE')->where('starts_at', '>', now('UTC'))->orderBy('starts_at')->get()
            ->map(fn (InterviewSlot $slot) => $this->formatSlotForTimezone($slot));
        $interview->load('candidate.lead');
        return Inertia::render('Public/InterviewBooking', [
            'candidate' => ['name' => $interview->candidate->lead->full_name, 'code' => $interview->candidate->code, 'type' => $interview->candidate->candidate_type->value],
            'slots' => $slots,
            'expiresAt' => $interview->invitation_expires_at,
            'actionUrl' => request()->fullUrl(),
        ]);
    }

    public function bookingSuccess(Request $request): Response
    {
        return Inertia::render('Public/InterviewBookingSuccess', [
            'candidate' => $request->session()->get('interview_booking_completed.candidate'),
            'scheduledAt' => $request->session()->get('interview_booking_completed.scheduled_at'),
            'whatsappUrl' => $request->session()->get('interview_booking_completed.whatsapp_url'),
        ]);
    }

    public function book(Request $request, Interview $interview, string $token): RedirectResponse
    {
        $this->validateInvitation($interview, $token);
        $validated = $request->validate(['slot_id' => ['required', 'integer', 'exists:interview_slots,id']]);
        try {
            $interview = DB::transaction(function () use ($interview, $validated) {
                $slot = InterviewSlot::whereKey($validated['slot_id'])->lockForUpdate()->firstOrFail();
                $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $slot->getRawOriginal('starts_at'), 'UTC');
                abort_if($slot->status !== 'AVAILABLE' || $slotStart->isPast(), 409, 'Este horario ya no está disponible. Elige otro.');
                $slot->update(['status' => 'BOOKED']);
                $interview->update(['interview_slot_id' => $slot->id, 'scheduled_at' => $slot->getRawOriginal('starts_at'), 'status' => 'SCHEDULED', 'confirmed_at' => now(), 'invitation_token_hash' => null]);
                $activity = CandidateActivity::create(['candidate_id' => $interview->candidate_id, 'type' => 'interview_scheduled', 'description' => 'La candidata agendó su entrevista.', 'metadata' => ['scheduled_at' => $slotStart->toIso8601String()]]);
                return $interview->fresh(['candidate.lead', 'slot']);
            });
        } catch (\Throwable $exception) {
            if ($exception->getCode() === '409' || str_contains($exception->getMessage(), 'Este horario')) return back()->withErrors(['slot_id' => 'Este horario ya no está disponible. Elige otro.']);
            throw $exception;
        }
        $whatsappUrl = $this->whatsappUrl($interview);
        $activity = $interview->candidate->activities()->latest('id')->first();
        app(RealtimePublisher::class)->publishLead($interview->candidate->lead, 'interview.scheduled', ['interview_id' => $interview->id, 'activity' => $activity?->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Entrevista agendada', 'description' => "{$interview->candidate->lead->full_name} agendó su entrevista."]]);
        try {
            Mail::to($interview->candidate->lead->email)->send(new InterviewScheduledMail($interview, $whatsappUrl));
        } catch (\Throwable $exception) {
            report($exception);
        }
        $scheduledAt = Carbon::createFromFormat('Y-m-d H:i:s', $interview->getRawOriginal('scheduled_at'), 'UTC')->setTimezone('America/Bogota')->toIso8601String();

        return to_route('interview.booking.success')->with('interview_booking_completed', [
            'candidate' => [
                'name' => $interview->candidate->lead->full_name,
                'code' => $interview->candidate->code,
            ],
            'scheduled_at' => $scheduledAt,
            'whatsapp_url' => $whatsappUrl,
        ]);
    }

    private function formatSlotForTimezone(InterviewSlot $slot): array
    {
        $timezone = 'America/Bogota';
        $startsAt = Carbon::createFromFormat('Y-m-d H:i:s', $slot->getRawOriginal('starts_at'), 'UTC')->setTimezone($timezone);
        $endsAt = Carbon::createFromFormat('Y-m-d H:i:s', $slot->getRawOriginal('ends_at'), 'UTC')->setTimezone($timezone);

        return array_merge($slot->toArray(), [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
        ]);
    }

    private function validateInvitation(Interview $interview, string $token): void
    {
        abort_unless($interview->status === 'INVITED' && $interview->invitation_token_hash && hash_equals($interview->invitation_token_hash, hash('sha256', $token)), 403, 'Este enlace no es válido.');
        abort_if($interview->invitation_expires_at?->isPast(), 410, 'Este enlace ya expiró.');
    }

    private function whatsappUrl(Interview $interview): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) config('services.velvet.whatsapp_number'));
        if (! $phone) return null;
        if (! str_starts_with($phone, '57')) $phone = '57' . $phone;
        $message = "Hola, soy {$interview->candidate->lead->full_name}. Necesito reprogramar mi entrevista de The Velvet Studio.";
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

}

