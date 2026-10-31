<?php

// PENDIENTE DE ACTIVACION: codigo comentado a la espera de que se vea el tema en clase.
// Para activarlo, descomentar este archivo y su registro en bootstrap/app.php.
//
// namespace App\Http\Middleware;
//
// use Closure;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;
// use Symfony\Component\HttpFoundation\Response;
//
// /**
//  * Registra en el log cada peticion a la API con su estado y duracion.
//  * No se guardan el cuerpo ni las cabeceras para no exponer contrasenas ni tokens.
//  */
// class RegistrarPeticionApi
// {
//     public function handle(Request $request, Closure $next): Response
//     {
//         $inicio = microtime(true);
//
//         $response = $next($request);
//
//         Log::info('Peticion API', [
//             'metodo'      => $request->method(),
//             'ruta'        => $request->path(),
//             'estado'      => $response->getStatusCode(),
//             'duracion_ms' => round((microtime(true) - $inicio) * 1000, 2),
//             'usuario_id'  => $request->user()?->id,
//         ]);
//
//         return $response;
//     }
// }
