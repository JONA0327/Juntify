<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPanelAdministrativo;
use App\Models\UserPanelMiembro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelController extends Controller
{
    protected function authorizeAdmin(Request $request): User
    {
        $admin = $request->user();

        if (! $admin || ! in_array($admin->roles, ['superadmin', 'developer'])) {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }

        return $admin;
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.panels');
    }

    public function list(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $panels = UserPanelAdministrativo::query()
            ->with('administrator')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UserPanelAdministrativo $panel) => $this->transformPanel($panel));

        return response()->json($panels);
    }

    public function eligibleAdmins(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $users = User::query()
            ->whereIn('roles', ['enterprise', 'founder', 'developer'])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'username', 'email', 'roles']);

        return response()->json($users->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->username,
                'email' => $user->email,
                'role' => $user->roles,
            ];
        }));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'administrator_id' => ['required', 'exists:users,id'],
            'panel_url' => ['required', 'url', 'max:255', 'unique:user_panel_administrativo,panel_url'],
        ]);

        $administrator = User::findOrFail($data['administrator_id']);

        if (! in_array($administrator->roles, ['enterprise', 'founder', 'developer'])) {
            return response()->json([
                'message' => 'El usuario seleccionado no tiene un rol válido para administrar un panel.',
            ], 422);
        }

        /** @var UserPanelAdministrativo $panel */
        $panel = DB::transaction(function () use ($data, $administrator) {
            $panel = UserPanelAdministrativo::create([
                'company_name' => $data['company_name'],
                'administrator_id' => $administrator->id,
                'panel_url' => $data['panel_url'],
            ]);

            UserPanelMiembro::create([
                'panel_id' => $panel->id,
                'user_id' => $administrator->id,
                'role' => 'administrador',
            ]);

            return $panel;
        });

        return response()->json($this->transformPanel($panel->fresh('administrator')));
    }

    protected function transformPanel(UserPanelAdministrativo $panel): array
    {
        $panel->refresh();

        return [
            'id' => $panel->id,
            'company_name' => $panel->company_name,
            'panel_url' => $panel->panel_url,
            'administrator' => $panel->administrator ? [
                'id' => $panel->administrator->id,
                'name' => $panel->administrator->full_name ?: $panel->administrator->username,
                'email' => $panel->administrator->email,
                'role' => $panel->administrator->roles,
            ] : null,
            'created_at' => optional($panel->created_at)->toIso8601String(),
            'updated_at' => optional($panel->updated_at)->toIso8601String(),
        ];
    }
}
