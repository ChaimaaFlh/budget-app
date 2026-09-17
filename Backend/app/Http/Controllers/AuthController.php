<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'departement_id' => 'required|exists:departements,id',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'departement_id' => $validated['departement_id'],
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès !',
            'user'    => $user->load('departement'),
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->is_active || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie !',
            'user'    => $user,
            'token'   => $token,
            'must_change_password' => $user->must_change_password,
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:12|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'Mot de passe mis à jour.'], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie !'], 200);
    }

    public function me(Request $request)
{
    $user = $request->user();
    $user->load(['departement', 'permissions']);

    $canViewAll = $user->hasDepartmentWideAccess();

    return response()->json([
        'user'         => $user->makeHidden('permissions'),
        'permissions'  => $user->permissions->pluck('code'),
        'departements' => $canViewAll
            ? \App\Models\Departement::all()
            : \App\Models\Departement::where('id', $user->departement_id)->get(),
    ], 200);
}
}
