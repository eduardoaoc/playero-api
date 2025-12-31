<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $normalizedRoles = $this->normalizeRoles($roles);
        if ($normalizedRoles !== [] && ! in_array(auth()->user()->role, $normalizedRoles, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }

    private function normalizeRoles(array $roles): array
    {
        $normalized = [];

        foreach ($roles as $role) {
            $parts = preg_split('/[|,]/', $role);
            foreach ($parts as $part) {
                $part = strtolower(trim((string) $part));
                if ($part !== '') {
                    $normalized[] = $part;
                }
            }
        }

        return array_values(array_unique($normalized));
    }
}
