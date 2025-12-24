<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Nao autenticado.',
                'data' => null,
            ], 401);
        }

        $normalizedRoles = [];
        foreach ($roles as $role) {
            $parts = preg_split('/[|,]/', $role);
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part !== '') {
                    $normalizedRoles[] = $part;
                }
            }
        }

        if (! empty($normalizedRoles) && ! $user->hasAnyRole($normalizedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Sem permissao.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
