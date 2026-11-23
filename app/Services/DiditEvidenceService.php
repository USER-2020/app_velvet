<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiditEvidenceService
{
    private const MAX_BYTES = 15_728_640;

    public function archive(Candidate $candidate, array $images): array
    {
        $disk = config('services.didit.evidence_disk', 'local');

        return array_values(array_filter(array_map(function (array $image) use ($candidate, $disk): ?array {
            $key = Str::slug((string) ($image['key'] ?? $image['label'] ?? 'evidence'));
            $path = data_get($image, 'path');
            $mimeType = $image['mime_type'] ?? null;

            if (! is_string($path) || ! Storage::disk($disk)->exists($path)) {
                $url = $image['source_url'] ?? $image['url'] ?? null;
                if (! is_string($url) || ! Str::startsWith($url, 'https://')) {
                    return null;
                }

                try {
                    $response = Http::timeout(20)->connectTimeout(10)->get($url);
                    $contents = $response->successful() ? $response->body() : null;
                    $mime = is_string($contents) ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) : false;
                    if (! is_string($contents) || strlen($contents) > self::MAX_BYTES || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
                        return null;
                    }

                    $extension = match ($mime) {
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'application/pdf' => 'pdf',
                        default => 'jpg',
                    };
                    $path = "didit-evidence/{$candidate->id}/{$key}.{$extension}";
                    Storage::disk($disk)->put($path, $contents);
                    $mimeType = $mime;
                } catch (\Throwable $exception) {
                    report($exception);
                    return null;
                }
            }

            return [
                'key' => $key,
                'label' => $image['label'] ?? 'Evidencia Didit',
                'path' => $path,
                'mime_type' => $mimeType ?: 'application/octet-stream',
                'url' => route('admin.validations.evidence', ['candidate' => $candidate->id, 'image' => $key]),
            ];
        }, $images)));
    }
}
