<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfilePhotoService
{
    private const DISK = 'local';
    private const MAX_BYTES = 5_242_880;

    public function storeUpload(User $user, UploadedFile $photo): void
    {
        $contents = file_get_contents($photo->getRealPath());
        abort_unless(is_string($contents) && $this->isSupportedImage($contents), 422, 'La imagen de perfil no es válida.');

        $this->replace($user, $contents, $this->extensionFor($contents), 'manual');
    }

    /** Copies Didit's temporary selfie/portrait into private storage when no photo has been chosen. */
    public function importDefaultFromDidit(Candidate $candidate): bool
    {
        $candidate->loadMissing('user');
        $user = $candidate->user;
        if (! $user || $user->profile_photo_path || $candidate->candidate_type?->value !== 'MODEL' || $candidate->identity_verification_status !== 'APPROVED') {
            return false;
        }

        $portrait = $this->portraitImage($candidate->identity_verification_data ?? []);
        if (! $portrait) {
            return false;
        }

        try {
            $disk = config('services.didit.evidence_disk', 'local');
            $contents = null;
            if (is_string($portrait['path'] ?? null) && Storage::disk($disk)->exists($portrait['path'])) {
                $contents = Storage::disk($disk)->get($portrait['path']);
            } elseif (is_string($portrait['url'] ?? null)) {
                $response = Http::timeout(10)->withoutRedirecting()->get($portrait['url']);
                $contents = $response->successful() ? $response->body() : null;
            }
            if (! is_string($contents) || strlen($contents) > self::MAX_BYTES || ! $this->isSupportedImage($contents)) {
                return false;
            }

            $this->replace($user, $contents, $this->extensionFor($contents), 'didit');

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function portraitImage(array $identityData): ?array
    {
        $images = collect($identityData['images'] ?? []);
        foreach (['selfie', 'facial', 'face', 'retrato', 'portrait'] as $needle) {
            $image = $images->first(fn ($item) => str_contains(mb_strtolower((string) ($item['label'] ?? '')), $needle));
            $url = $image['url'] ?? null;
            if (is_string($image['path'] ?? null) || (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && Str::startsWith($url, 'https://'))) {
                return $image;
            }
        }

        return null;
    }

    private function replace(User $user, string $contents, string $extension, string $source): void
    {
        $path = "profile-photos/{$user->id}/" . Str::uuid() . ".{$extension}";
        Storage::disk(self::DISK)->put($path, $contents);
        $previous = $user->profile_photo_path;
        $user->forceFill(['profile_photo_path' => $path, 'profile_photo_source' => $source])->save();

        if ($previous && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }
    }

    private function isSupportedImage(string $contents): bool
    {
        $image = @getimagesizefromstring($contents);

        return is_array($image) && in_array($image[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
    }

    private function extensionFor(string $contents): string
    {
        return match (getimagesizefromstring($contents)[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
    }
}
