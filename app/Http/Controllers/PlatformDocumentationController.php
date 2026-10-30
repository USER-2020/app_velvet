<?php

namespace App\Http\Controllers;

use App\Models\PlatformDocumentation;
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
        ['key' => 'deployment', 'title' => 'Despliegue seguro', 'category' => 'Operación', 'description' => 'Preparación y verificación de Velvet Master en producción.', 'path' => 'DESPLIEGUE_PRODUCCION_SEGURO.md'],
        ['key' => 'roadmap', 'title' => 'Roadmap y pendientes', 'category' => 'Plataforma', 'description' => 'Estado de implementación, próximos hitos y restricciones conocidas.', 'path' => 'VELVET_MASTER_ROADMAP.md'],
    ];

    public function index(Request $request): Response
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

        return Inertia::render('Admin/Documentation/Index', [
            'references' => $references,
            'customDocuments' => $custom,
            'modules' => $this->modules(),
            'canManage' => $request->user()->hasRole('super_admin'),
            'openapi' => $openapi,
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
        $path = base_path('docs/'.$reference['path']);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($path, '.json') ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.addslashes($reference['path']).'"',
        ]);
    }

    private function modules(): array
    {
        return [
            ['name' => 'Reclutamiento', 'description' => 'Leads, candidatos, entrevistas, contratación, calendario y pipeline.', 'route' => '/admin/recruitment'],
            ['name' => 'Onboarding', 'description' => 'Procesos, validaciones y documentos del ingreso.', 'route' => '/admin/processes'],
            ['name' => 'Personas', 'description' => 'Modelos, monitores, perfiles operativos y seguimiento.', 'route' => '/admin/candidates'],
            ['name' => 'Operación diaria', 'description' => 'Turnos, asistencia, incidencias, solicitudes y checklists.', 'route' => '/admin/operations'],
            ['name' => 'Plataformas', 'description' => 'Cuentas de estudio, simulaciones e importaciones manuales.', 'route' => '/admin/platforms'],
            ['name' => 'Rooms', 'description' => 'Sedes, salas, reservas y uso operativo.', 'route' => '/admin/rooms'],
            ['name' => 'Administración', 'description' => 'Usuarios, roles, permisos, configuración y visitas guiadas.', 'route' => '/admin/access'],
            ['name' => 'Productividad', 'description' => 'Sesiones, transmisiones y métricas operativas.', 'route' => '/admin/productivity'],
        ];
    }
}
