<?php

namespace App\Http\Controllers;

use App\Services\GroqChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GroqAssistantController extends Controller
{
    public function index(Request $request, GroqChatService $groq): Response
    {
        abort_unless($request->user()->hasPermissionTo('ai.manage'), 403);

        return Inertia::render('Admin/AI/Index', ['settings' => $groq->settings()]);
    }

    public function update(Request $request, GroqChatService $groq)
    {
        abort_unless($request->user()->hasPermissionTo('ai.manage'), 403);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'model' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'models' => ['required', 'array', 'min:1', 'max:4'],
            'models.*' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'auto_select' => ['required', 'boolean'],
            'max_tokens' => ['required', 'integer', 'between:100,2000'],
            'temperature' => ['required', 'numeric', 'between:0.1,1'],
        ]);
        $groq->updateSettings([...$data, 'models' => array_values(array_unique([$data['model'], ...$data['models']]))]);

        return to_route('admin.ai.index')->with('success', 'Configuración del asistente actualizada.');
    }

    public function chat(Request $request, GroqChatService $groq): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('ai.chat'), 403);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:4000'],
            'path' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            return response()->json(['message' => $groq->complete($request->user(), $data['message'], $data['history'] ?? [], $data['path'] ?? null)]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'El asistente no está disponible en este momento. Intenta nuevamente más tarde.'], 503);
        }
    }
}
