<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserAccessController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Access/Users', [
            'users' => User::with('roles')->orderBy('name')->paginate(20),
            'roles' => $this->availableRoles($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Access/UserForm', [
            'user' => null,
            'roles' => $this->availableRoles($request->user()),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        abort_unless($this->canManageUser($request->user(), $user), 403);

        return Inertia::render('Admin/Access/UserForm', [
            'user' => $user->load('roles'),
            'roles' => $this->availableRoles($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $availableSlugs = $this->availableRoles($request->user())->pluck('slug')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($availableSlugs)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->syncRoles($data['roles']);
        app(AuditLogger::class)->record('access.user_created', $user, ['role_slugs' => $data['roles']]);

        return to_route('admin.users.index')->with('success', 'Usuario creado y roles asignados.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->canManageUser($request->user(), $user), 403);

        $availableSlugs = $this->availableRoles($request->user())->pluck('slug')->all();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($availableSlugs)],
        ]);

        $user->fill(collect($data)->only(['name', 'email'])->all());
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();
        $user->syncRoles($data['roles']);
        app(AuditLogger::class)->record('access.user_updated', $user, ['role_slugs' => $data['roles']]);

        return to_route('admin.users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->canManageUser($request->user(), $user), 403);
        abort_if($request->user()->is($user), 422, 'No puedes eliminar tu propio usuario.');

        app(AuditLogger::class)->record('access.user_deleted', $user, ['email' => $user->email]);
        $user->delete();

        return to_route('admin.users.index')->with('success', 'Usuario eliminado.');
    }

    private function availableRoles(?User $actor)
    {
        $query = Role::query()->orderBy('name');

        if ($actor?->hasRole('super_admin')) {
            return $query->get();
        }

        if ($actor?->hasRole('admin')) {
            return $query->where('slug', '!=', 'super_admin')->get();
        }

        if ($actor?->hasRole('manager')) {
            return $query->whereIn('slug', ['recruiter', 'hr', 'monitor', 'model'])->get();
        }

        return $query->whereRaw('1 = 0')->get();
    }

    private function canManageUser(?User $actor, User $target): bool
    {
        if ($actor?->hasRole('super_admin')) {
            return true;
        }

        if ($actor?->hasRole('admin')) {
            return ! $target->hasRole('super_admin');
        }

        if ($actor?->hasRole('manager')) {
            return $target->roles()->whereIn('slug', ['super_admin', 'admin', 'manager'])->doesntExist();
        }

        return false;
    }
}
