<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password
            && !$request->is('api/me', 'api/me/password', 'api/logout')) {
            return response()->json([
                'message' => 'Vous devez modifier votre mot de passe temporaire avant de continuer.',
            ], 403);
        }

        return $next($request);
    }
}
