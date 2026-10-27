<?php

namespace App\Http\Controllers;

use App\Models\UserTourProgress;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class TourProgressController extends Controller
{
    private const TOUR_KEYS = [
        'platform-welcome',
        'pipeline',
        'leads',
        'lead-flow',
        'candidates',
        'candidate-flow',
        'interviews',
        'contracting',
        'onboarding',
        'validations',
        'documents',
        'calendar',
        'rooms',
        'operations',
        'platforms',
        'productivity',
        'administration',
        'workflows',
        'training-data',
        'guided-tours',
        'documentation',
        'agent',
        'users',
        'roles',
        'notifications',
        'profile',
        'monitor-portal',
        'monitor-rooms',
        'monitor-models',
        'model-portal',
        'model-rooms',
        'model-profile',
        'work-sessions',
    ];

    public function update(Request $request, string $tourKey)
    {
        abort_unless(in_array($tourKey, self::TOUR_KEYS, true), 404);
        abort_unless(SystemSetting::guidedToursEnabled(), 403);

        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(['completed', 'skipped'])],
        ]);

        try {
            UserTourProgress::updateOrCreate(
                ['user_id' => $request->user()->id, 'tour_key' => $tourKey],
                [
                    'version' => $data['version'],
                    'completed_at' => $data['status'] === 'completed' ? now() : null,
                    'skipped_at' => $data['status'] === 'skipped' ? now() : null,
                ]
            );
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '42S02') {
                throw $exception;
            }
        }

        return response()->noContent();
    }
}
