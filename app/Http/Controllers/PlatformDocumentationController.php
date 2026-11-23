<?php

namespace App\Http\Controllers;

use App\Models\PlatformDocumentation;
use App\Services\DashboardGuides;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDocumentationController extends Controller
{
    private const REFERENCES = [
        ['key' => 'integration', 'title' => 'Integración VELVET Agent', 'category' => 'APIs', 'description' => 'Enrolamiento, login OAuth PKCE, sesiones de trabajo, heartbeat, métricas, comandos, revocación e idempotencia.', 'path' => 'AGENT_INTEGRATION.md'],
        ['key' => 'openapi', 'title' => 'Contrato OpenAPI de Agent', 'category' => 'APIs', 'description' => 'Contrato versionado de las APIs de enrolamiento, OAuth y telemetría.', 'path' => 'agent.openapi.json'],
        ['key' => 'oauth', 'title' => 'Login OAuth de VELVET Agent', 'category' => 'APIs', 'description' => 'Guía de navegador, callback loopback, PKCE, renovación y revocación de sesiones.', 'path' => 'AGENT_OAUTH.md'],
        ['key' => 'sessions', 'title' => 'Sesiones y métricas de Agent', 'category' => 'APIs', 'description' => 'Registro por usuario del tiempo conectado, transmisión, OBS, cierres y tolerancia a reconexiones.', 'path' => 'AGENT_SESSIONS.md'],
        ['key' => 'platform-guide', 'title' => 'Guía de Velvet Master', 'category' => 'Plataforma', 'description' => 'Manual funcional, técnico y de QA de la plataforma.', 'path' => 'guia-plataforma.md'],
        ['key' => 'permissions', 'title' => 'Permisos operativos', 'category' => 'Administración', 'description' => 'Roles, permisos y alcance de los módulos operativos.', 'path' => 'VELVET_MASTER_PERMISSIONS.md'],
        ['key' => 'ai-assistant', 'title' => 'Asistente IA: endpoints y permisos', 'category' => 'IA y seguridad', 'description' => 'Endpoints, controladores, contexto de solo lectura, permisos y límites del asistente.', 'path' => 'AI_ASSISTANT.md'],
        ['key' => 'deployment', 'title' => 'Despliegue seguro', 'category' => 'Operación', 'description' => 'Preparación y verificación de Velvet Master en producción.', 'path' => 'DESPLIEGUE_PRODUCCION_SEGURO.md'],
        ['key' => 'queue-operations', 'title' => 'Colas y scheduler en producción', 'category' => 'Operación', 'description' => 'Worker de correos, scheduler de Agent, monitoreo, reintentos y limpieza segura desde consola.', 'path' => 'OPERACION_COLAS_Y_SCHEDULER_PRODUCCION.md'],
        ['key' => 'roadmap', 'title' => 'Roadmap y pendientes', 'category' => 'Plataforma', 'description' => 'Estado de implementación, próximos hitos y restricciones conocidas.', 'path' => 'VELVET_MASTER_ROADMAP.md'],
        ['key' => 'module-standard', 'title' => 'Estándar de módulos y demostraciones', 'category' => 'Desarrollo', 'description' => 'Contrato para integrar módulos sin romper flujos, documentarlos por rol y publicar guías Driver.js, HyperFrames y MP4.', 'path' => 'MODULE_DOCUMENTATION_STANDARD.md'],
        ['key' => 'pwa-responsive', 'title' => 'PWA y responsive móvil', 'category' => 'Plataforma', 'description' => 'Instalación PWA, actualización segura, tema, áreas seguras y prevención del autozoom en iPhone.', 'path' => 'PWA_RESPONSIVE.md'],
    ];

    public function index(Request $request, DashboardGuides $guides): Response
    {
        $custom = PlatformDocumentation::query()->with('author:id,name')->latest()->get()->map(fn (PlatformDocumentation $item) => [
            'id' => $item->id,
            'title' => $item->title,
            'category' => $item->category,
            'summary' => $item->summary,
            'content' => $item->content,
            'attachment_name' => $item->attachment_name,
            'attachment_mime' => $item->attachment_mime,
            'attachment_size' => $item->attachment_size,
            'author' => $item->author?->name,
            'created_at' => $item->created_at?->toIso8601String(),
            'download_url' => $item->attachment_path ? route('admin.documentation.download', $item) : null,
        ])->values();

        $references = collect(self::REFERENCES)->map(fn (array $reference) => [...$reference, 'url' => route('admin.documentation.reference', $reference['key'])]);
        $openapiPath = base_path('docs/agent.openapi.json');
        $openapi = is_file($openapiPath) ? json_decode(file_get_contents($openapiPath), true) : null;
        $referencePreview = null;
        $referenceKey = $request->string('reference')->value();
        $reference = collect(self::REFERENCES)->firstWhere('key', $referenceKey);
        if ($reference && str_ends_with($reference['path'], '.md')) {
            $referencePath = base_path('docs/'.$reference['path']);
            if (is_file($referencePath)) {
                $referencePreview = [
                    'key' => $reference['key'],
                    'title' => $reference['title'],
                    'category' => $reference['category'],
                    'path' => $reference['path'],
                    'content' => file_get_contents($referencePath),
                ];
            }
        }

        return Inertia::render('Admin/Documentation/Index', [
            'references' => $references,
            'customDocuments' => $custom,
            'guides' => $guides->forUser($request->user())->values(),
            'guideProfiles' => $guides->profiles($request->user()),
            'canManage' => $request->user()->hasRole('super_admin'),
            'openapi' => $openapi,
            'referencePreview' => $referencePreview,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:80'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:500000'],
            'attachment' => ['nullable', 'file', 'max:51200'],
        ]);

        $attachment = $request->file('attachment');
        if ($attachment) {
            $data['attachment_path'] = $attachment->store('platform-documentation', 'local');
            $data['attachment_name'] = $attachment->getClientOriginalName();
            $data['attachment_mime'] = $attachment->getMimeType();
            $data['attachment_size'] = $attachment->getSize();
        }
        unset($data['attachment']);
        $data['created_by'] = $request->user()->id;

        PlatformDocumentation::create($data);

        return back()->with('success', 'La documentación se guardó correctamente.');
    }

    public function destroy(PlatformDocumentation $documentation)
    {
        if ($documentation->attachment_path) {
            Storage::disk('local')->delete($documentation->attachment_path);
        }
        $documentation->delete();

        return back()->with('success', 'La documentación se eliminó correctamente.');
    }

    public function download(PlatformDocumentation $documentation)
    {
        abort_unless($documentation->attachment_path && Storage::disk('local')->exists($documentation->attachment_path), 404);

        return Storage::disk('local')->download($documentation->attachment_path, $documentation->attachment_name, ['Content-Type' => $documentation->attachment_mime]);
    }

    public function reference(string $key)
    {
        $reference = collect(self::REFERENCES)->firstWhere('key', $key);
        abort_unless($reference, 404);

        if ($key === 'openapi') {
            return redirect()->route('admin.documentation.index', ['openapi' => 1]);
        }

        if (str_ends_with($reference['path'], '.md')) {
            return redirect()->route('admin.documentation.index', ['reference' => $key]);
        }

        $path = base_path('docs/'.$reference['path']);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($path, '.json') ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.addslashes($reference['path']).'"',
        ]);
    }

    public function guide(Request $request, DashboardGuides $guides, string $guide)
    {
        $flow = $guides->forUser($request->user())->get($guide);
        abort_unless($flow, 404);

        $download = $request->boolean('download');
        $response = response()->view('documentation.guide', [
            'flow' => $flow,
            'preview' => ! $download,
        ], 200, ['Cache-Control' => 'private, no-store']);

        if ($download) {
            $response->header('Content-Disposition', 'attachment; filename="velvet-'.$flow['key'].'.html"');
        }

        return $response;
    }

    public function guideVideo(Request $request, DashboardGuides $guides, string $guide)
    {
        $flow = $guides->forUser($request->user())->get($guide);
        abort_unless($flow && $flow['video_url'], 404);

        return response()->file(resource_path("documentation/videos/{$flow['key']}.mp4"), [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
