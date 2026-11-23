<?php

namespace App\Services\Platforms;

use App\Models\StudioAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ChaturbateApiService
{
    private const STATS_URL = 'https://chaturbate.com/statsapi/';
    private const AFFILIATE_STATS_URL = 'https://chaturbate.com/affiliates/apistats/';

    public function credentialsFromInput(array $input): array
    {
        $statsUrl = trim((string) ($input['stats_url'] ?? ''));
        $eventsUrl = trim((string) ($input['events_url'] ?? ''));
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $token = trim((string) ($input['token'] ?? ''));

        if ($statsUrl !== '') {
            $parts = parse_url($statsUrl);
            parse_str($parts['query'] ?? '', $query);
            $username = strtolower(trim((string) ($query['username'] ?? $username)));
            $token = trim((string) ($query['token'] ?? $token));
        }

        if ($eventsUrl !== '') {
            $parts = parse_url($eventsUrl);
            $segments = array_values(array_filter(explode('/', trim($parts['path'] ?? '', '/'))));
            if (($segments[0] ?? null) === 'events' && $username === '') {
                $username = strtolower(trim((string) ($segments[1] ?? $username)));
            }
        }

        if ($username === '' || ! preg_match('/^[a-z0-9_]+$/', $username)) {
            throw new RuntimeException('El usuario de Chaturbate no es válido.');
        }

        if ($token === '' || ! preg_match('/^[A-Za-z0-9]{24}$/', $token)) {
            throw new RuntimeException('El token de Chaturbate debe tener 24 caracteres.');
        }

        return [
            'username' => $username,
            'token' => $token,
            'stats_url' => self::STATS_URL . '?username=' . rawurlencode($username) . '&token=' . rawurlencode($token),
            'events_url' => $eventsUrl !== '' ? $eventsUrl : 'https://eventsapi.chaturbate.com/events/' . rawurlencode($username) . '/' . rawurlencode($token) . '/',
        ];
    }

    public function verify(array $credentials): array
    {
        $response = Http::timeout(15)->get(self::STATS_URL, [
            'username' => $credentials['username'],
            'token' => $credentials['token'],
        ]);

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('Chaturbate rechazó las credenciales o no respondió con JSON.');
        }

        $payload = $response->json();
        if (isset($payload['error'])) {
            throw new RuntimeException('Chaturbate rechazó el token.');
        }

        $eventsResponse = Http::timeout(10)->get($credentials['events_url'], ['timeout' => 0]);
        if (! $eventsResponse->successful() || ! is_array($eventsResponse->json())) {
            throw new RuntimeException('Chaturbate Stats API respondió, pero Events API no está disponible para este token.');
        }

        return $payload;
    }

    public function stats(StudioAccount $account): array
    {
        $credentials = $this->credentials($account);
        return $this->verify($credentials);
    }

    public function credentials(StudioAccount $account): array
    {
        $decoded = json_decode(decrypt($account->encrypted_credentials), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || empty($decoded['username']) || empty($decoded['token'])) {
            throw new RuntimeException('La cuenta no tiene credenciales de Chaturbate configuradas.');
        }
        return $decoded;
    }

    public function publicMetadata(array $stats): array
    {
        return collect($stats)->only([
            'username', 'token_balance', 'tips_in_last_hour', 'votes_up', 'votes_down',
            'satisfaction_score', 'last_broadcast', 'time_online', 'num_followers',
            'num_viewers', 'num_registered_viewers', 'num_tokened_viewers',
        ])->all();
    }
}
