<?php

// PENDIENTE DE ACTIVACION: codigo comentado a la espera de que se vea el tema en clase.
// Para activarlo, descomentar este archivo y su registro en bootstrap/app.php.
//
// namespace App\Http\Middleware;
//
// use Closure;
// use Illuminate\Http\Request;
// use Symfony\Component\HttpFoundation\Response;
//
// /**
//  * Obliga a que la API responda en JSON aunque el cliente no envie la cabecera Accept,
//  * evitando redirecciones o paginas HTML cuando ocurre un error de autenticacion o validacion.
//  */
// class ForzarRespuestaJson
// {
//     public function handle(Request $request, Closure $next): Response
//     {
//         $request->headers->set('Accept', 'application/json');
//
//         return $next($request);
//     }
// }
