<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuidedTourSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Admin/GuidedTours/Index', [
            'enabled' => SystemSetting::guidedToursEnabled(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        SystemSetting::setGuidedToursEnabled($data['enabled']);

        return to_route('admin.guided-tours.index')->with(
            'success',
            $data['enabled'] ? 'Las visitas guiadas se activaron para todos los usuarios.' : 'Las visitas guiadas se desactivaron para todos los usuarios.',
        );
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole(['super_admin', 'super-admin']), 403);
    }
}
