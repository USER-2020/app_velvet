<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessControlController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Access/Index', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('name')->get(),
            'permissionCount' => Permission::count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Access/RoleForm', [
            'role' => null,
            'permissionGroups' => $this->permissionGroups(),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $role = Role::create(collect($data)->except('permissions')->all());
        $role->permissions()->sync($data['permissions'] ?? []);
        app(AuditLogger::class)->record('access.role_created', $role, ['permission_ids' => $data['permissions'] ?? []]);

        return to_route('admin.access.index')->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/Access/RoleForm', [
            'role' => $role->load('permissions'),
            'permissionGroups' => $this->permissionGroups(),
            'roles' => Role::where('id', '!=', $role->id)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);
        $role->update(collect($data)->except('permissions')->all());
        $role->permissions()->sync($data['permissions'] ?? []);
        app(AuditLogger::class)->record('access.role_updated', $role, ['permission_ids' => $data['permissions'] ?? []]);

        return to_route('admin.access.index')->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 422, 'Los roles del sistema no se pueden eliminar.');
        app(AuditLogger::class)->record('access.role_deleted', $role, ['role_slug' => $role->slug]);
        $role->delete();

        return to_route('admin.access.index')->with('success', 'Rol eliminado correctamente.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:roles,slug,'.($role?->id ?? 'NULL')],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_role_id' => ['nullable', 'integer', 'exists:roles,id', 'different:'.$role?->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
    }

    private function permissionGroups(): array
    {
        return Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group')->map(fn ($items, $group) => [
            'group' => $group,
            'permissions' => $items->values(),
        ])->values()->all();
    }
}
