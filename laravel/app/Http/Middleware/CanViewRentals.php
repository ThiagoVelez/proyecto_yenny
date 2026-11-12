<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para el Perfil Cliente.
 * Verifica manualmente el rol del usuario autenticado ($request->user()->role).
 * Metodos REST permitidos para Cliente: login, consultarCobro, consultarCobrosPorAlquiler, consultarPagosPorCobro.
 * Metodo SOAP permitido: consultarAlquileres.
 * Roles con autorizacion para consultar alquileres: Administrador, Operador de Recepcion y Cliente.
 */
class CanViewRentals
{
    /**
     * Metodos REST autorizados para el rol de Cliente.
     */
    protected const METODOS_PERMITIDOS_CLIENTE = [
        'login',
        'consultarcobro',
        'consultarcobrosporalquiler',
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

        // El Operador de recepcion tambien tiene permiso para ver alquileres
        $isOperador = in_array($role, ['operador', 'operador_recepcion', 'operador de recepcion'], true);
        $isCliente = ($role === 'cliente');

        if (! $isOperador && ! $isCliente) {
            // El rol Cajero u otros no autorizados no pueden consultar alquileres
            return response()->json([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ], 403);
        }

        // 3. Si el rol es cliente, validar que no intente ejecutar metodos fuera de su lista
        if ($isCliente) {
            $actionName = strtolower(trim($metodo ?: $this->resolveActionName($request)));

            if (! in_array($actionName, self::METODOS_PERMITIDOS_CLIENTE, true)) {
                return response()->json([
                    'error' => 'Permisos insuficientes para ejecutar este método',
                ], 403);
            }
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

        if ($routeName === 'cobros.byAlquiler' || ($method === 'GET' && str_contains($path, 'cobros/alquiler'))) {
            return 'consultarcobrosporalquiler';
        }
        if ($routeName === 'cobros.show' || ($method === 'GET' && preg_match('#^api/cobros/\d+$#', $path))) {
            return 'consultarcobro';
        }
        if ($routeName === 'pagos.byCobro' || ($method === 'GET' && str_contains($path, 'pagos/cobro'))) {
            return 'consultarpagosporcobro';
        }
        if ($routeName === 'cobros.store' || ($method === 'POST' && str_ends_with($path, 'cobros'))) {
            return 'generarcobro';
        }
        if ($routeName === 'pagos.store' || ($method === 'POST' && str_ends_with($path, 'pagos'))) {
            return 'registrarpago';
        }

        return (string) ($request->route()?->getActionMethod() ?: 'consultarcobrosporalquiler');
    }
}
