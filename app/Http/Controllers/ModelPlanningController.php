<?php

namespace App\Http\Controllers;

use App\Enums\CandidateType;
use App\Models\Candidate;
use App\Models\ModelProjection;
use App\Models\WishlistItem;
use App\Services\RealtimePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ModelPlanningController extends Controller
{
    public function model(Request $request): Response
    {
        $candidate = Candidate::where('user_id', $request->user()->id)->where('candidate_type', CandidateType::MODEL->value)->first();
        abort_unless($candidate, 403, 'Tu usuario todavía no está vinculado a una modelo.');
        return Inertia::render('Portal/Model/Planning', [...$this->payload($candidate, true, false), 'transmissionProgress' => $this->transmissionProgress($candidate)]);
    }

    public function createWishlistShare(Request $request)
    {
        $candidate = $this->ownCandidate($request);
        if (! $candidate->wishlist_share_token) {
            $candidate->update(['wishlist_share_token' => Str::random(64)]);
        }
        return response()->json(['url' => url('/wishlist/share/'.$candidate->wishlist_share_token)]);
    }

    public function publicWishlist(string $token): Response
    {
        $candidate = Candidate::query()->with(['lead', 'wishlistItems' => fn ($query) => $query->latest()])->where('wishlist_share_token', $token)->where('candidate_type', CandidateType::MODEL->value)->firstOrFail();
        return Inertia::render('Public/Wishlist/Show', [
            'model' => ['name' => trim(($candidate->lead?->first_name ?? '') . ' ' . ($candidate->lead?->last_name ?? '')) ?: $candidate->code],
            'wishlist' => $candidate->wishlistItems->map(function (WishlistItem $item) use ($token) {
                $payload = collect($item->toArray())->except(['target_date', 'image_path'])->all();
                $payload['image_url'] = $item->image_path ? url('/wishlist/share/'.$token.'/image/'.$item->id) : $item->image_url;
                return $payload;
            })->values(),
        ]);
    }

    public function publicWishlistImage(string $token, WishlistItem $item)
    {
        abort_unless($item->candidate?->wishlist_share_token === $token, 404);
        abort_unless($item->image_path && Storage::disk('local')->exists($item->image_path), 404);
        return Storage::disk('local')->response($item->image_path, null, ['Cache-Control' => 'public, max-age=3600']);
    }

    public function team(Request $request): Response
    {
        $query = Candidate::query()->with(['lead', 'wishlistItems' => fn ($wishlist) => $wishlist->latest()])->where('candidate_type', CandidateType::MODEL->value)->whereNotIn('status', ['DISCARDED', 'WITHDRAWN']);
        if ($request->user()->hasRole('monitor')) {
            $monitorId = Candidate::where('user_id', $request->user()->id)->value('id');
            $query->whereHas('modelAssignments', fn ($assignment) => $assignment->where('monitor_candidate_id', $monitorId)->whereNull('ended_at'));
        }
        $teamCandidates = $query->orderBy('id')->get();
        $models = $teamCandidates->map(fn (Candidate $candidate) => [
            'id' => $candidate->id,
            'name' => trim(($candidate->lead?->first_name ?? '') . ' ' . ($candidate->lead?->last_name ?? '')) ?: $candidate->code,
            'code' => $candidate->code,
            'wishlist_count' => $candidate->wishlistItems->count(),
            'strategy_count' => $candidate->projections()->count(),
        ])->values();
        $allWishlists = $teamCandidates->map(function (Candidate $candidate) {
            $model = [
                'id' => $candidate->id,
                'name' => trim(($candidate->lead?->first_name ?? '') . ' ' . ($candidate->lead?->last_name ?? '')) ?: $candidate->code,
                'code' => $candidate->code,
            ];

            return [
                'model' => $model,
                'wishlist' => $candidate->wishlistItems->map(function (WishlistItem $item) {
                    $payload = collect($item->toArray())->except(['target_date', 'image_path'])->all();
                    $payload['image_url'] = $item->image_path ? url('/planning/wishlist/'.$item->id.'/image') . '?v=' . optional($item->updated_at)->timestamp : $item->image_url;
                    return $payload;
                })->values(),
            ];
        })->filter(fn (array $group) => $group['wishlist']->isNotEmpty())->values();

        $selectedId = $request->integer('model');
        $candidate = $selectedId ? $teamCandidates->firstWhere('id', $selectedId) : null;
        return Inertia::render('Admin/ModelPlanning/Index', ['models' => $models, 'allWishlists' => $allWishlists, 'selectedModel' => $candidate ? [...$this->payload($candidate, false, true), 'transmissionProgress' => $this->transmissionProgress($candidate)] : null, 'canEdit' => false, 'canManageStrategy' => $candidate ? $this->canManageTeamCandidate($request, $candidate) : false]);
    }

    public function storeWishlist(Request $request, RealtimePublisher $realtime): RedirectResponse
    {
        $candidate = $this->ownCandidate($request)->load('lead');
        $item = $candidate->wishlistItems()->create($this->normalizeWishlist($request->validate($this->wishlistRules())));
        $name = trim(($candidate->lead?->first_name ?? '') . ' ' . ($candidate->lead?->last_name ?? '')) ?: $candidate->code;
        $realtime->publish('wishlist.created', [
            'candidate_id' => $candidate->id,
            'wishlist_item_id' => $item->id,
            'notification' => [
                'title' => 'Nueva wishlist de modelo',
                'description' => "{$name} agregó “{$item->title}” a su wishlist.",
            ],
            'notification_roles' => ['admin', 'super_admin'],
        ]);
        return back()->with('success', 'Elemento agregado a tu wishlist.');
    }

    public function linkPreview(Request $request)
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:1000']]);
        $url = $data['url'];
        $parts = parse_url($url);
        abort_unless(in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true), 422, 'El enlace debe usar HTTP o HTTPS.');
        $host = $parts['host'] ?? '';
        abort_unless($host && ! in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true), 422, 'No se puede consultar este dominio.');

        $response = Http::timeout(8)->connectTimeout(4)->withHeaders(['User-Agent' => 'VelvetWishlistPreview/1.0'])->get($url);
        if (! $response->successful()) {
            return response()->json(['available' => false, 'message' => 'No encontramos metadata pública en este enlace.'], 422);
        }

        $document = new \DOMDocument();
        @$document->loadHTML(mb_substr($response->body(), 0, 1_500_000));
        $metadata = [];
        foreach ($document->getElementsByTagName('meta') as $tag) {
            $key = strtolower($tag->getAttribute('property') ?: $tag->getAttribute('name'));
            $value = trim($tag->getAttribute('content'));
            if ($key && $value && in_array($key, ['og:title', 'og:description', 'og:image', 'og:site_name', 'product:price:amount', 'product:price:currency', 'twitter:title', 'twitter:description', 'twitter:image'], true)) {
                $metadata[$key] = $value;
            }
        }
        $title = $metadata['og:title'] ?? $metadata['twitter:title'] ?? trim($document->getElementsByTagName('title')->item(0)?->textContent ?? '');
        $preview = ['available' => (bool) ($title || ($metadata['og:image'] ?? $metadata['twitter:image'] ?? null) || ($metadata['og:description'] ?? $metadata['twitter:description'] ?? null)), 'title' => $title ?: null, 'description' => $metadata['og:description'] ?? $metadata['twitter:description'] ?? null, 'image' => $metadata['og:image'] ?? $metadata['twitter:image'] ?? null, 'site_name' => $metadata['og:site_name'] ?? parse_url($url, PHP_URL_HOST), 'price' => $metadata['product:price:amount'] ?? null, 'currency' => $metadata['product:price:currency'] ?? null, 'source_url' => $url];

        return response()->json($preview);
    }

    public function updateWishlist(Request $request, WishlistItem $item, RealtimePublisher $realtime): RedirectResponse
    {
        $candidate = $this->ownCandidate($request)->load('lead');
        abort_unless($item->candidate_id === $candidate->id, 403);
        $item->update($this->normalizeWishlist($request->validate($this->wishlistRules()), $item));
        $name = trim(($candidate->lead?->first_name ?? '') . ' ' . ($candidate->lead?->last_name ?? '')) ?: $candidate->code;
        $realtime->publish('wishlist.updated', [
            'candidate_id' => $candidate->id,
            'wishlist_item_id' => $item->id,
            'notification' => [
                'title' => 'Wishlist de modelo actualizada',
                'description' => "{$name} actualizó “{$item->title}” en su wishlist.",
            ],
            'notification_roles' => ['admin', 'super_admin'],
        ]);
        return back()->with('success', 'Wishlist actualizada.');
    }

    public function destroyWishlist(Request $request, WishlistItem $item): RedirectResponse
    {
        abort_unless($item->candidate_id === $this->ownCandidate($request)->id, 403);
        if ($item->image_path) Storage::disk('local')->delete($item->image_path);
        $item->delete();
        return back()->with('success', 'Elemento eliminado.');
    }

    public function wishlistImage(Request $request, WishlistItem $item)
    {
        abort_unless($this->canViewCandidate($request, $item->candidate), 403);
        abort_unless($item->image_path && Storage::disk('local')->exists($item->image_path), 404);
        return Storage::disk('local')->response($item->image_path);
    }

    public function storeProjection(Request $request, RealtimePublisher $realtime): RedirectResponse
    {
        $candidate = $this->teamCandidate($request)->load('lead');
        $projection = $candidate->projections()->create($this->normalizeProjection($request->validate($this->projectionRules())));
        $this->publishStrategyNotification($realtime, 'strategy.created', $candidate, $projection, 'creó una estrategia');
        return back()->with('success', 'Estrategia creada.');
    }

    public function updateProjection(Request $request, ModelProjection $projection, RealtimePublisher $realtime): RedirectResponse
    {
        $candidate = $this->teamCandidate($request, $projection->candidate_id)->load('lead');
        $projection->update($this->normalizeProjection($request->validate($this->projectionRules())));
        $this->publishStrategyNotification($realtime, 'strategy.updated', $candidate, $projection, 'actualizó una estrategia');
        return back()->with('success', 'Estrategia actualizada.');
    }

    public function destroyProjection(Request $request, ModelProjection $projection): RedirectResponse
    {
        $this->teamCandidate($request, $projection->candidate_id);
        $projection->delete();
        return back()->with('success', 'Estrategia eliminada.');
    }

    private function payload(Candidate $candidate, bool $canEdit, bool $includeStrategies): array
    {
        $candidate->load(['lead', 'wishlistItems' => fn ($q) => $q->latest()]);
        if ($includeStrategies) $candidate->load(['projections' => fn ($q) => $q->latest()]);
        return ['model' => ['id' => $candidate->id, 'name' => trim(($candidate->lead?->first_name ?? '') . ' ' . ($candidate->lead?->last_name ?? '')) ?: $candidate->code, 'code' => $candidate->code], 'wishlist' => $candidate->wishlistItems->map(function (WishlistItem $item) { $payload = collect($item->toArray())->except(['target_date', 'image_path'])->all(); $payload['image_url'] = $item->image_path ? url('/planning/wishlist/'.$item->id.'/image') . '?v=' . optional($item->updated_at)->timestamp : $item->image_url; return $payload; })->values(), 'strategies' => $includeStrategies ? $candidate->projections->map(fn (ModelProjection $item) => collect($item->toArray())->except('target_date')->all())->values() : [], 'canEdit' => $canEdit];
    }

    private function ownCandidate(Request $request): Candidate
    {
        return Candidate::where('user_id', $request->user()->id)->where('candidate_type', CandidateType::MODEL->value)->firstOrFail();
    }

    private function teamCandidate(Request $request, ?int $candidateId = null): Candidate
    {
        $candidate = Candidate::query()->where('candidate_type', CandidateType::MODEL->value)->findOrFail($candidateId ?: $request->integer('candidate_id'));
        abort_unless($this->canManageTeamCandidate($request, $candidate), 403);
        return $candidate;
    }

    private function canManageTeamCandidate(Request $request, Candidate $candidate): bool
    {
        if ($request->user()->hasRole(['admin', 'super_admin'])) return true;
        if (! $request->user()->hasRole('monitor')) return false;
        $monitorId = Candidate::where('user_id', $request->user()->id)->value('id');
        return (bool) $monitorId && $candidate->modelAssignments()->where('monitor_candidate_id', $monitorId)->whereNull('ended_at')->exists();
    }

    private function publishStrategyNotification(RealtimePublisher $realtime, string $event, Candidate $candidate, ModelProjection $projection, string $action): void
    {
        $name = trim(($candidate->lead?->first_name ?? '') . ' ' . ($candidate->lead?->last_name ?? '')) ?: $candidate->code;
        $realtime->publish($event, ['candidate_id' => $candidate->id, 'strategy_id' => $projection->id, 'notification' => ['title' => 'Estrategia de modelo actualizada', 'description' => "Un responsable {$action} para {$name}: “{$projection->title}”."], 'notification_roles' => ['admin', 'super_admin']]);
    }

    private function wishlistRules(): array
    {
        return ['title' => ['required', 'string', 'max:160'], 'category' => ['nullable', 'string', 'max:60'], 'description' => ['nullable', 'string', 'max:2000'], 'url' => ['nullable', 'url', 'max:1000'], 'image_url' => ['nullable', 'url', 'max:1000'], 'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'estimated_price' => ['nullable', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3'], 'priority' => ['required', 'in:low,medium,high'], 'target_date' => ['nullable', 'date'], 'status' => ['required', 'in:planned,active,achieved'], 'metadata' => ['nullable', 'array']];
    }

    private function projectionRules(): array
    {
        return ['title' => ['required', 'string', 'max:160'], 'type' => ['required', 'in:wishlist_goal,purchase,savings,growth,retention,production,travel,experience,financial,other'], 'description' => ['nullable', 'string', 'max:2000'], 'target_amount' => ['nullable', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3'], 'status' => ['required', 'in:draft,active,achieved'], 'urgency' => ['required', 'in:low,medium,high,critical'], 'mind_map' => ['nullable', 'array'], 'mind_map.nodes' => ['nullable', 'array', 'max:30'], 'mind_map.nodes.*.label' => ['required_with:mind_map.nodes', 'string', 'max:160'], 'metadata' => ['nullable', 'array'], 'metadata.wishlist_item_ids' => ['nullable', 'array'], 'metadata.wishlist_item_ids.*' => ['integer']];
    }

    private function normalizeWishlist(array $data, ?WishlistItem $item = null): array
    {
        if (! empty($data['image_file'])) {
            if ($item?->image_path) Storage::disk('local')->delete($item->image_path);
            $data['image_path'] = $data['image_file']->store('wishlist', 'local');
            $data['image_url'] = null;
        } elseif (! empty($data['image_url'])) {
            $privatePath = $this->storeRemoteWishlistImage($data['image_url']);
            if ($privatePath) {
                if ($item?->image_path) Storage::disk('local')->delete($item->image_path);
                $data['image_path'] = $privatePath;
            }
            // Las imágenes persistidas nunca quedan como una URL pública externa.
            $data['image_url'] = null;
        }
        unset($data['image_file'], $data['_method']);
        return $data + ['metadata' => []];
    }

    private function storeRemoteWishlistImage(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! $host || in_array($host, ['localhost', 'localhost.localdomain'], true)) return null;

        try {
            $response = Http::timeout(8)->connectTimeout(4)->withHeaders(['User-Agent' => 'VelvetWishlistImage/1.0'])->get($url);
            $contentType = strtolower((string) $response->header('Content-Type'));
            $body = $response->body();
            if (! $response->successful() || ! str_starts_with($contentType, 'image/') || strlen($body) > 5 * 1024 * 1024) return null;

            $extension = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'svg') => 'svg',
                default => 'jpg',
            };
            $path = 'wishlist/' . Str::uuid() . '.' . $extension;
            Storage::disk('local')->put($path, $body);
            return $path;
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }
    }

    private function canViewCandidate(Request $request, Candidate $candidate): bool
    {
        if ($request->user()->hasRole(['admin', 'super_admin'])) return true;
        if ($candidate->user_id === $request->user()->id) return true;
        if (! $request->user()->hasRole('monitor')) return false;
        $monitorId = Candidate::where('user_id', $request->user()->id)->value('id');
        return (bool) $monitorId && $candidate->modelAssignments()->where('monitor_candidate_id', $monitorId)->whereNull('ended_at')->exists();
    }
    private function normalizeProjection(array $data): array { unset($data['candidate_id'], $data['_method']); $data['mind_map'] = $data['mind_map'] ?? ['nodes' => []]; return $data + ['metadata' => []]; }

    private function transmissionProgress(Candidate $candidate): array
    {
        $monthStart = now('America/Bogota')->startOfMonth()->utc();
        $sessionIds = DB::table('operational_sessions')->where('candidate_id', $candidate->id)->where('started_at', '>=', $monthStart)->pluck('id');
        $streams = $sessionIds->isEmpty() ? 0 : DB::table('stream_sessions')->whereIn('operational_session_id', $sessionIds)->count();
        $tokens = $sessionIds->isEmpty() ? 0 : DB::table('production_metrics')->whereIn('operational_session_id', $sessionIds)->where('metric_key', 'tokens')->sum('metric_value');
        return ['sessions' => $sessionIds->count(), 'streams' => $streams, 'tokens' => (float) $tokens, 'period' => 'Este mes'];
    }
}
