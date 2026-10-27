<?php

namespace App\Services\Agent;

use App\Models\Agent\AgentIdempotencyKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class Idempotency
{
    public static function hash(array $body): string
    {
        $sort = function (array $value) use (&$sort): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($body), JSON_THROW_ON_ERROR));
    }

    public function run(Request $request, string $scope, callable $action, bool $enrollment = false, ?callable $replay = null)
    {
        $key = $request->header('Idempotency-Key');
        abort_if(Validator::make(['key' => $key], ['key' => 'required|string|max:100|regex:/^[A-Za-z0-9._:-]+$/'])->fails(), 400, 'Idempotency-Key is required.');
        $hash = self::hash($request->json()->all());

        return DB::transaction(function () use ($scope, $action, $enrollment, $replay, $key, $hash) {
            AgentIdempotencyKey::query()->insertOrIgnore([
                'scope' => $scope, 'key' => $key, 'body_hash' => $hash,
                'expires_at' => now()->addMinutes($enrollment ? config('agent.replay_minutes') : config('agent.idempotency_hours') * 60),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $record = AgentIdempotencyKey::where('scope', $scope)->where('key', $key)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($record->body_hash, $hash), 409, 'Idempotency key body conflict.');
            if ($record->status !== null) {
                abort_if(! $record->expires_at->isFuture(), 409, 'Idempotency window expired; reconcile before retrying.');
                $body = json_decode(Crypt::decryptString($record->response), true, 512, JSON_THROW_ON_ERROR);
                if ($replay) {
                    $replay($body);
                }

                return response()->json($body, $record->status)->header('Cache-Control', 'no-store');
            }
            [$body, $status] = $action();
            $record->update(['response' => Crypt::encryptString(json_encode($body, JSON_THROW_ON_ERROR)), 'status' => $status]);

            return response()->json($body, $status)->header('Cache-Control', 'no-store');
        }, 3);
    }
}
