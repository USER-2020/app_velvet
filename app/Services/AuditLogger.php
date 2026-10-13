<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogger
{
    /**
     * Persist an audit event without retaining credentials, tokens or uploaded content.
     * Audit failures must never block the operational transaction that produced the event.
     */
    public function record(string $event, ?Model $subject = null, array $context = [], ?User $actor = null): void
    {
        try {
            $request = app()->bound('request') ? app('request') : null;

            AuditLog::create([
                'event' => $event,
                'actor_user_id' => $actor?->id ?? $request?->user()?->id,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'context' => $this->redact($context),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function redact(array $context): array
    {
        $sensitive = ['password', 'credential', 'secret', 'token', 'authorization', 'document', 'file', 'content'];

        return Arr::map($context, function ($value, string|int $key) use ($sensitive) {
            foreach ($sensitive as $fragment) {
                if (str_contains(mb_strtolower((string) $key), $fragment)) {
                    return '[REDACTED]';
                }
            }

            return is_array($value) ? $this->redact($value) : $value;
        });
    }
}
