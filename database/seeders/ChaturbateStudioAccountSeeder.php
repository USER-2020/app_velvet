<?php

namespace Database\Seeders;

use App\Models\PlatformCatalog;
use App\Models\StudioAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class ChaturbateStudioAccountSeeder extends Seeder
{
    public function run(): void
    {
        $username = strtolower(trim((string) env('CHATURBATE_USERNAME', '')));
        $token = $this->tokenFromValue((string) env('CHATURBATE_TOKEN', ''), 'stats');
        $eventsToken = $this->tokenFromValue((string) env('CHATURBATE_EVENTS_TOKEN', $token), 'events');

        if ($username === '' || $token === '') {
            $this->command?->warn('ChaturbateStudioAccountSeeder omitida: faltan CHATURBATE_USERNAME o CHATURBATE_TOKEN.');
            return;
        }

        $platform = PlatformCatalog::updateOrCreate(
            ['code' => 'chaturbate'],
            ['name' => 'Chaturbate', 'adapter_key' => 'chaturbate', 'is_active' => true]
        );

        $credentials = [
            'username' => $username,
            'token' => $token,
            'stats_url' => 'https://chaturbate.com/statsapi/?username=' . rawurlencode($username) . '&token=' . rawurlencode($token),
            'events_url' => 'https://eventsapi.chaturbate.com/events/' . rawurlencode($username) . '/' . rawurlencode($eventsToken) . '/',
        ];

        StudioAccount::updateOrCreate(
            ['platform_catalog_id' => $platform->id, 'name' => env('CHATURBATE_ACCOUNT_NAME', 'Cuenta Studio Chaturbate')],
            [
                'legal_holder' => env('CHATURBATE_LEGAL_HOLDER'),
                'payment_receiver' => env('CHATURBATE_PAYMENT_RECEIVER'),
                'account_type' => 'STUDIO',
                'status' => 'CONNECTED',
                'connection_mode' => 'LIVE',
                'provider_key' => 'chaturbate',
                'encrypted_credentials' => Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)),
                'credentials_verified_at' => now(),
                'last_synced_at' => null,
                'last_error' => null,
            ]
        );

        $this->command?->info("Cuenta Studio de Chaturbate preparada para {$username}.");
    }

    private function tokenFromValue(string $value, string $kind): string
    {
        $value = trim($value);
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $parts = parse_url($value);
            if ($kind === 'stats') {
                parse_str($parts['query'] ?? '', $query);
                return trim((string) ($query['token'] ?? ''));
            }

            $segments = array_values(array_filter(explode('/', trim($parts['path'] ?? '', '/'))));
            return trim((string) ($segments[2] ?? ''));
        }

        return $value;
    }
}
