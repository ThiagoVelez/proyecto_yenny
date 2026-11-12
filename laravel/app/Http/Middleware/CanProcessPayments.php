<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para el Perfil Cajero.
 * Verifica manualmente el rol del usuario autenticado ($request->user()->role).
 * Metodos REST permitidos para Cajero: login, consultarCobro, registrarPago, consultarPagosPorCobro.
 * Metodo SOAP permitido: Ninguno.
 * Roles con autorizacion para procesar pagos: Administrador y Cajero.
 */
class CanProcessPayments
{
    /**
     * Metodos REST autorizados para el rol de Cajero.
     */
    protected const METODOS_PERMITIDOS_CAJERO = [
        'login',
        'consultarcobro',
        'registrarpago',
        'consultarpagosporcobro',
    ];

    /**
     * Maneja la peticion entrante.
     *
     * @param Request $request Peticion HTTP entrante
     * @param Closure $next Siguiente middleware en la cadena
     * @param string|null $metodo Nombre explicito del metodo evaluado (opcional)
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?string $metodo = null): Response
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

        // El Administrador tiene acceso a todos los metodos
        if (in_array($role, ['admin', 'administrador'], true)) {
            return $next($request);
        }

        // 3. Determinar el metodo que se intenta ejecutar
        $actionName = strtolower(trim($metodo ?: $this->resolveActionName($request)));

        // Si la accion es consultar pagos de un cobro, el cliente tambien tiene permiso de lectura
        if (in_array($actionName, ['consultarpagosporcobro', 'consultarcobro'], true) && $role === 'cliente') {
            return $next($request);
        }

        // Roles no autorizados para procesar pagos (como operador o cliente intentando registrar pago)
        $isCajero = ($role === 'cajero');
        if (! $isCajero) {
            return response()->json([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ], 403);
        }

        if (! in_array($actionName, self::METODOS_PERMITIDOS_CAJERO, true)) {
            return response()->json([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Resuelve el nombre del metodo a partir de la ruta o accion del controlador.
     */
    protected function resolveActionName(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());

        if ($routeName === 'pagos.store' || ($method === 'POST' && str_ends_with($path, 'pagos'))) {
            return 'registrarpago';
        }
        if ($routeName === 'pagos.byCobro' || ($method === 'GET' && str_contains($path, 'pagos/cobro'))) {
            return 'consultarpagosporcobro';
        }
        if ($routeName === 'cobros.show' || ($method === 'GET' && preg_match('#^api/cobros/\d+$#', $path))) {
            return 'consultarcobro';
        }
        if ($routeName === 'cobros.store' || ($method === 'POST' && str_ends_with($path, 'cobros'))) {
            return 'generarcobro';
        }
        if ($routeName === 'cobros.byAlquiler' || ($method === 'GET' && str_contains($path, 'cobros/alquiler'))) {
            return 'consultarcobrosporalquiler';
        }

        return (string) ($request->route()?->getActionMethod() ?: 'registrarpago');
    }
}
