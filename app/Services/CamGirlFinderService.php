<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CamGirlFinderService
{
    public function discover(Candidate $candidate): array
    {
        $headers = ['User-Agent' => config('services.camgirlfinder.user_agent')];
        $imageCheck = $this->checkWithDiditImage($candidate, null, $headers);

        return [
            'provider' => 'camgirlfinder',
            'status' => $imageCheck['source'] === 'didit' ? ($imageCheck['matches']->isNotEmpty() ? 'found' : 'not_found') : 'pending',
            'image_source' => $imageCheck['source'],
            'matches' => $imageCheck['matches']->take(20)->map(fn (array $match) => $this->publicMatch($match))->values()->all(),
        ];
    }

    public function validateNickname(string $nickname, string $platform): array
    {
        $nickname = trim($nickname);
        $platform = $this->platformCode($platform);
        $headers = ['User-Agent' => config('services.camgirlfinder.user_agent')];
        $response = Http::timeout(12)->connectTimeout(5)->withHeaders($headers)->get($this->url('/models/search'), array_filter(['model' => $nickname, 'platform' => $platform]));

        if ($response->status() === 429) throw new RuntimeException('CamGirlFinder limitó temporalmente la consulta.');
        if (! $response->successful() || ! is_array($response->json())) throw new RuntimeException('CamGirlFinder no está disponible para validar el nickname.');

        $matches = collect($response->json())->filter(fn ($match) => is_array($match))->values();
        $exact = $matches->first(fn (array $match) => Str::lower((string) ($match['name'] ?? '')) === Str::lower($nickname)
            && (! $platform || Str::lower((string) ($match['platform'] ?? '')) === Str::lower($platform)));

        return [
            'provider' => 'camgirlfinder',
            'status' => $exact ? 'found' : 'not_found',
            'nickname' => $nickname,
            'platform' => $platform,
            'exact_name_match' => (bool) $exact,
            'matches' => $matches->take(5)->map(fn (array $match) => $this->publicMatch($match))->values()->all(),
        ];
    }

    public function check(Candidate $candidate, string $nickname, string $platform): array
    {
        $imageCheck = $this->discover($candidate);
        $nameCheck = $this->validateNickname($nickname, $platform);

        return [
            ...$nameCheck,
            'image_match' => $imageCheck['status'] === 'found',
            'image_matches' => $imageCheck['matches'],
            'image_source' => $imageCheck['image_source'],
        ];
    }

    private function checkWithDiditImage(Candidate $candidate, ?string $platform, array $headers): array
    {
        $portrait = $this->diditPortrait($candidate);
        if (! $portrait) {
            return ['source' => 'unavailable', 'matches' => collect()];
        }

        $response = Http::timeout(25)->connectTimeout(8)->withHeaders($headers)
            ->attach('image', $portrait['contents'], 'didit-portrait.' . $portrait['extension'], ['Content-Type' => $portrait['mime_type']])
            ->post($this->url('/search'));

        if ($response->status() === 429) {
            throw new RuntimeException('CamGirlFinder limitó temporalmente la búsqueda visual.');
        }
        if (! $response->successful() || ! is_array($response->json())) {
            return ['source' => 'unavailable', 'matches' => collect()];
        }

        $jobId = $response->json('id');
        if (! is_string($jobId) || $jobId === '') {
            return ['source' => 'unavailable', 'matches' => collect()];
        }

        $job = null;
        for ($attempt = 0; $attempt < 6; $attempt++) {
            if ($attempt > 0) usleep(500000);
            $jobResponse = Http::timeout(10)->connectTimeout(5)->withHeaders($headers)->get($this->url('/jobs/'.$jobId));
            if (! $jobResponse->successful() || ! is_array($jobResponse->json())) continue;
            $job = $jobResponse->json();
            if (($job['status'] ?? null) !== 'active') break;
        }

        $matches = collect(data_get($job, 'predictions', []))->filter(fn ($match) => is_array($match))
            ->when($platform, fn ($matches) => $matches->filter(fn (array $match) => Str::lower((string) ($match['platform'] ?? '')) === Str::lower($platform)))
            ->values();

        return ['source' => 'didit', 'matches' => $matches];
    }

    private function diditPortrait(Candidate $candidate): ?array
    {
        $images = collect(data_get($candidate->identity_verification_data, 'images', []));
        $portrait = $images->first(function ($image) {
            $label = Str::lower((string) ($image['label'] ?? ''));
            return collect(['selfie', 'facial', 'face', 'retrato', 'portrait'])->contains(fn (string $needle) => Str::contains($label, $needle));
        });
        if (! is_array($portrait)) return null;

        $disk = config('services.didit.evidence_disk', 'local');
        $contents = null;
        if (is_string($portrait['path'] ?? null) && Storage::disk($disk)->exists($portrait['path'])) {
            $contents = Storage::disk($disk)->get($portrait['path']);
        } elseif (is_string($portrait['url'] ?? null) && Str::startsWith($portrait['url'], 'https://')) {
            $response = Http::timeout(10)->get($portrait['url']);
            $contents = $response->successful() ? $response->body() : null;
        }
        if (! is_string($contents) || strlen($contents) > 10 * 1024 * 1024) return null;

        $image = @getimagesizefromstring($contents);
        if (! is_array($image)) return null;
        $mimeType = image_type_to_mime_type($image[2] ?? 0);
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) return null;

        return [
            'contents' => $contents,
            'mime_type' => $mimeType,
            'extension' => match ($mimeType) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            },
        ];
    }

    private function publicMatch(array $match): array
    {
        return collect($match)->only(['name', 'model', 'platform', 'distance', 'probability', 'seen', 'accountSeen', 'urls'])->all();
    }

    private function platformCode(string $platform): ?string
    {
        return match (Str::lower(trim($platform))) {
            'chaturbate', 'cb' => 'cb',
            'myfreecams', 'mfc' => 'mfc',
            'stripchat', 'sc' => 'sc',
            'camsoda', 'cs' => 'cs',
            default => Str::lower(trim($platform)) ?: null,
        };
    }

    private function url(string $path): string
    {
        return rtrim(config('services.camgirlfinder.base_url'), '/') . '/' . ltrim($path, '/');
    }
}
