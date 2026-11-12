<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para el Perfil Administrador.
 * Verifica manualmente el rol del usuario autenticado ($request->user()->role).
 * El Administrador tiene acceso total a todos los metodos REST y a la operacion SOAP consultarAlquileres.
 */
class IsAdministrator
{
    /**
     * Maneja la peticion entrante.
     *
     * @param Request $request Peticion HTTP entrante
     * @param Closure $next Siguiente middleware en la cadena
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Validacion de autenticacion (Token Bearer)
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 2. Verificacion manual del rol del usuario ($request->user()->role)
        $role = strtolower(trim((string) ($user->role ?? '')));

        if (! in_array($role, ['admin', 'administrador'], true)) {
            return response()->json([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ], 403);
        }

        return $next($request);
    }
}
