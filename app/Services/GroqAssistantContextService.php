<?php

namespace App\Services;

use App\Models\PlatformDocumentation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroqAssistantContextService
{
    public function __construct(
        private readonly RecruitmentDashboardService $recruitmentDashboard,
    ) {
    }

    public function build(User $user, string $message): string
    {
        $sections = [];

        if ($this->asksForStatistics($message)) {
            $statistics = $this->recruitmentStatistics($user);
            if ($statistics !== null) {
                $sections[] = "DATOS OPERATIVOS AUTORIZADOS (últimos 30 días):\n".json_encode($statistics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        if ($this->asksForDocumentation($message)) {
            $documentation = $this->documentation($message);
            if ($documentation !== '') {
                $sections[] = "DOCUMENTACIÓN INTERNA AUTORIZADA:\n".$documentation;
            }
        }

        if ($sections === []) {
            return '';
        }

        return "\n\nContexto verificado por Velvet Master. Usa únicamente estos datos para responder:\n"
            .implode("\n\n", $sections);
    }

    private function recruitmentStatistics(User $user): ?array
    {
        if (! $user->hasRole(['admin', 'super_admin']) && ! $user->hasPermissionTo('candidates.view_all')) {
            return null;
        }

        $payload = $this->recruitmentDashboard->handle(new Request(['period' => '30d', 'type' => 'ALL']));

        return [
            'periodo' => $payload['filters'],
            'metricas' => collect($payload['metrics'])->mapWithKeys(fn (array $metric) => [$metric['key'] => [
                'etiqueta' => $metric['label'],
                'valor' => $metric['value'],
                'variacion_porcentual' => $metric['change'],
            ]])->all(),
            'pipeline' => collect($payload['pipeline'])->map(fn (array $stage) => [
                'etapa' => $stage['label'],
                'cantidad' => $stage['count'],
            ])->values()->all(),
            'distribucion' => collect($payload['distribution'])->map(fn (array $item) => [
                'tipo' => $item['label'],
                'cantidad' => $item['count'],
            ])->values()->all(),
        ];
    }

    private function documentation(string $message): string
    {
        $terms = collect(preg_split('/\s+/u', Str::lower($message)) ?: [])
            ->map(fn (string $term) => trim($term, " \t\n\r\0\x0B.,;:!?¿¡()[]{}"))
            ->filter(fn (string $term) => mb_strlen($term) >= 4)
            ->values();

        $references = [
            'guia-plataforma.md',
            'VELVET_MASTER_PERMISSIONS.md',
            'AGENT_INTEGRATION.md',
            'AGENT_SESSIONS.md',
            'DESPLIEGUE_PRODUCCION_SEGURO.md',
            'VELVET_MASTER_ROADMAP.md',
            'MODULE_DOCUMENTATION_STANDARD.md',
            'PWA_RESPONSIVE.md',
        ];

        $documents = collect($references)->map(function (string $filename) use ($terms): ?array {
            $path = base_path('docs/'.$filename);
            if (! is_file($path)) {
                return null;
            }

            $content = file_get_contents($path);
            $normalized = Str::lower($content ?: '');
            $matches = $terms->isEmpty() || $terms->contains(fn (string $term) => Str::contains($normalized, $term));

            return $matches ? ['title' => $filename, 'content' => mb_substr($content ?: '', 0, 6000)] : null;
        })->filter()->values();

        $custom = PlatformDocumentation::query()
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhere('title', 'like', '%'.$term.'%')
                        ->orWhere('summary', 'like', '%'.$term.'%')
                        ->orWhere('content', 'like', '%'.$term.'%');
                }
            })
            ->latest()
            ->limit(3)
            ->get(['title', 'summary', 'content'])
            ->map(fn (PlatformDocumentation $document) => [
                'title' => $document->title,
                'content' => mb_substr(trim(($document->summary ? $document->summary."\n" : '').($document->content ?: '')), 0, 6000),
            ]);

        return $documents->merge($custom)->take(3)->map(fn (array $document) => "### {$document['title']}\n{$document['content']}")->implode("\n\n");
    }

    private function asksForStatistics(string $message): bool
    {
        return (bool) preg_match('/\b(estad[ií]stica|estad[ií]sticas|m[eé]trica|m[eé]tricas|cu[aá]ntos|cu[aá]ntas|total|candidatos|leads|pipeline|entrevistas|onboarding|activos)\b/iu', $message);
    }

    private function asksForDocumentation(string $message): bool
    {
        return (bool) preg_match('/\b(documentaci[oó]n|manual|gu[ií]a|permiso|permisos|rol|roles|m[oó]dulo|m[oó]dulos|hyperframes|video|api|endpoint|funciona|funcionamiento|despliegue)\b/iu', $message);
    }
}
