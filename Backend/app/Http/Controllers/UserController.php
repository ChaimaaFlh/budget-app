<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdministrator($request);

        return response()->json(
            User::with(['departement', 'permissions'])->orderByDesc('created_at')->orderByDesc('id')->get(),
            200
        );
    }

    public function store(Request $request)
    {
        $this->ensureAdministrator($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|confirmed',
            'departement_id' => 'required|exists:departements,id',
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => 'Compte créé. L’utilisateur devra modifier son mot de passe à sa première connexion.',
            'data' => $user->load('departement'),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAdministrator($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'departement_id' => 'required|exists:departements,id',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Utilisateur mis à jour avec succès !',
            'data' => $user->load('departement'),
        ], 200);
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->ensureAdministrator($request);

        if ($user->is($request->user())) {
            return response()->json(['message' => 'Vous ne pouvez pas désactiver votre propre compte.'], 422);
        }

        $validated = $request->validate(['is_active' => 'required|boolean']);
        $user->update($validated);

        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Statut du compte mis à jour.', 'data' => $user], 200);
    }

    private function ensureAdministrator(Request $request): void
    {
        if (!$request->user()->hasPermission('user.manage_permissions')) {
            abort(response()->json([
                'message' => "Action non autorisée : permission 'user.manage_permissions' requise.",
            ], 403));
        }
    }
}