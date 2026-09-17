<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permissionCode)
    {
        if (!$request->user() || !$request->user()->hasPermission($permissionCode)) {
            return response()->json([
                'message' => "Action non autorisée : permission '{$permissionCode}' requise.",
            ], 403);
        }

        return $next($request);
    }
}