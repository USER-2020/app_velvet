<?php

namespace App\Services\Agent;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AgentPayload
{
    public static function validate(Request $request, array $rules): array
    {
        $validator = Validator::make($request->json()->all(), $rules);
        $validator->after(function ($validator) use ($request) {
            foreach ($validator->getRules() as $attribute => $rules) {
                $value = data_get($request->json()->all(), $attribute);
                if ($value === null) {
                    continue;
                }
                foreach ($rules as $rule) {
                    $valid = match ($rule) {
                        'integer' => is_int($value),
                        'numeric' => (is_int($value) || is_float($value)) && is_finite((float) $value),
                        'boolean' => is_bool($value),
                        default => true,
                    };
                    if (! $valid) {
                        $validator->errors()->add($attribute, 'Incorrect JSON primitive type.');
                    }
                }
            }
        });
        // Never echo rejected input (in particular enrollment tokens).
        abort_if($validator->fails(), 400, 'Invalid agent payload: '.implode(', ', $validator->errors()->keys()));

        return $validator->validated();
    }

    public static function time(bool $nullable = false, bool $optional = false): array
    {
        return [$optional ? 'sometimes' : ($nullable ? 'present' : 'required'), ...($nullable ? ['nullable'] : []), function ($attribute, $value, $fail) {
            if (! is_string($value) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,7})?(?:Z|[+-](?:0\\d|1[0-4]):[0-5]\\d)$/D', $value)) {
                $fail('An RFC3339 timestamp with at most seven fractional digits is required.');

                return;
            }
            if (! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
                || (int) substr($value, 11, 2) > 23 || (int) substr($value, 14, 2) > 59 || (int) substr($value, 17, 2) > 59
                || preg_match('/[+-]14:(?!00)/', $value)) {
                $fail('Invalid calendar date, time or offset.');

                return;
            }
            // .NET DateTimeOffset has 100 ns precision; storage uses microseconds.
            $normalized = preg_replace('/(\\.\\d{6})\\d/', '$1', $value);
            try {
                $date = Carbon::parse($normalized);
                if ($date->format('Y-m-d') !== substr($value, 0, 10) || $date->isAfter(now()->addMinutes(5))) {
                    $fail('Invalid date or clock skew exceeds five minutes.');
                }
            } catch (\Throwable) {
                $fail('Invalid timestamp.');
            }
        }];
    }

    public static function clean(string $message): string
    {
        $message = strip_tags($message);
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);
        $message = preg_replace('/Bearer\s+[^\s,;]+/i', 'Bearer [redacted]', $message);
        $message = preg_replace('/((?:password|token|secret|authorization|api[_-]?key)\s*[=:]\s*)[^\s,;]+/i', '$1[redacted]', $message);
        $message = preg_replace('/\b(?:vea|ved)_[A-Za-z0-9_-]+\b/', '[redacted]', $message);

        return $message;
    }

    public static function heartbeatRules(): array
    {
        return [
            'id' => 'required|uuid', 'sentAt' => self::time(),
            'sessionId' => 'sometimes|nullable|uuid', 'active' => 'sometimes|boolean',
            'status' => 'required|array:identity,enrollment,masterConnected,lastSync,version,uptimeSeconds,metrics,obs,mode,userAuthenticated,userId,userDisplayName,userRole,configurationAvailable,sessionId,sessionStartedAt',
            'status.identity' => 'required|array:installationId,hostname',
            'status.identity.installationId' => 'required|uuid', 'status.identity.hostname' => 'required|string|max:255',
            'status.enrollment' => 'required|integer|in:0,1,2', 'status.masterConnected' => 'required|boolean',
            'status.lastSync' => self::time(true), 'status.version' => 'required|string|max:100',
            'status.uptimeSeconds' => 'required|numeric|min:0|max:1000000000000',
            'status.mode' => 'required|in:development-adapter,master-api',
            'status.metrics' => 'required|array:hostname,operatingSystem,architecture,cpuCount,cpuPercent,ramTotalBytes,ramAvailableBytes,ramUsedPercent,diskAvailableBytes',
            'status.metrics.hostname' => 'required|string|max:255', 'status.metrics.operatingSystem' => 'required|string|max:255',
            'status.metrics.architecture' => 'required|string|max:100', 'status.metrics.cpuCount' => 'required|integer|min:1|max:65536',
            'status.metrics.cpuPercent' => 'present|nullable|numeric|between:0,100',
            'status.metrics.ramUsedPercent' => 'present|nullable|numeric|between:0,100',
            'status.metrics.ramTotalBytes' => 'present|nullable|integer|min:0|max:9223372036854775807',
            'status.metrics.ramAvailableBytes' => 'present|nullable|integer|min:0|max:9223372036854775807',
            'status.metrics.diskAvailableBytes' => 'present|nullable|integer|min:0|max:9223372036854775807',
            'status.obs' => 'required|array:processRunning,webSocketConnected,streaming,currentScene,scenes,error',
            'status.obs.processRunning' => 'required|boolean', 'status.obs.webSocketConnected' => 'required|boolean',
            'status.obs.streaming' => 'present|nullable|boolean', 'status.obs.currentScene' => 'present|nullable|string|max:255',
            'status.obs.scenes' => 'present|nullable|array|max:100', 'status.obs.scenes.*' => 'string|max:255',
            'status.obs.error' => 'present|nullable|string|max:2000',
            'status.userAuthenticated' => 'sometimes|boolean',
            'status.userId' => 'sometimes|nullable|string|max:255',
            'status.userDisplayName' => 'sometimes|nullable|string|max:255',
            'status.userRole' => 'sometimes|nullable|string|max:100',
            'status.configurationAvailable' => 'sometimes|boolean',
            'status.sessionId' => 'sometimes|nullable|uuid',
            'status.sessionStartedAt' => self::time(true, true),
        ];
    }
}
