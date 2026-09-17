<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        return response()->json(
            Permission::where('code', '!=', 'categorie.manage')->orderBy('code')->get(),
            200,
        );
    }

    public function userPermissions(Request $request, string $userId)
    {
        if (!$request->user()->hasPermission('user.manage_permissions')) {
            return response()->json(['message' => "Action non autorisée : permission 'user.manage_permissions' requise."], 403);
        }

        $user = User::with('permissions')->findOrFail($userId);
        return response()->json($user->permissions, 200);
    }

    public function updateUserPermissions(Request $request, string $userId)
    {
        if (!$request->user()->hasPermission('user.manage_permissions')) {
            return response()->json(['message' => "Action non autorisée : permission 'user.manage_permissions' requise."], 403);
        }
        
        $user = User::findOrFail($userId);

        if ($user->is($request->user())) {
            return response()->json([
                'message' => 'Vous ne pouvez pas modifier vos propres permissions.',
            ], 422);
        }

        $validated = $request->validate([
            'permission_ids'   => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $viewAllId  = Permission::where('code', 'departement.view_all')->value('id');
        $manageId   = Permission::where('code', 'user.manage_permissions')->value('id');

        if ($viewAllId
            && in_array($viewAllId, $validated['permission_ids'])
            && !in_array($manageId, $validated['permission_ids'])) {
            return response()->json([
                'message' => "La permission 'departement.view_all' ne peut être attribuée qu'au profil Administrateur (nécessite 'user.manage_permissions').",
            ], 422);
        }

        $user->permissions()->sync($validated['permission_ids']);

        $user->load('permissions');
        
        return response()->json([
            'message' => 'Permissions mises à jour.',
            'data'    => $user->permissions,
        ], 200);
    }
}
