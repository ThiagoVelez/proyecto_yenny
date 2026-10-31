<?php

// PENDIENTE DE ACTIVACION: codigo comentado a la espera de que se vea el tema en clase.
// Para activarlo, descomentar este archivo y su registro en bootstrap/app.php.
//
// namespace App\Http\Middleware;
//
// use Closure;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Http\Request;
// use Symfony\Component\HttpFoundation\Response;
//
// /**
//  * Exige que el token Sanctum del usuario tenga todas las capacidades indicadas en la ruta.
//  * Debe ejecutarse despues de auth:sanctum para que el usuario ya este resuelto.
//  *
//  * Ejemplo de uso: Route::middleware(['auth:sanctum', 'capacidad.token:cobros:escribir'])
//  */
// class VerificarCapacidadToken
// {
//     public function handle(Request $request, Closure $next, string ...$capacidades): Response
//     {
//         $usuario = $request->user();
//
//         if (! $usuario) {
//             return $this->rechazar('No autenticado.', 401);
//         }
//
//         foreach ($capacidades as $capacidad) {
//             if (! $usuario->tokenCan($capacidad)) {
//                 return $this->rechazar('El token no tiene la capacidad requerida: ' . $capacidad . '.', 403);
//             }
//         }
//
//         return $next($request);
//     }
//
//     private function rechazar(string $mensaje, int $codigo): JsonResponse
//     {
//         return response()->json([
//             'success' => false,
//             'message' => $mensaje,
//         ], $codigo);
//     }
// }
