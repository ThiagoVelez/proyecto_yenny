<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Valida credenciales, busca el usuario mediante el repositorio y emite un token
     * con capacidades limitadas a la operacion de consulta.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->userRepository->findByEmail($data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        // El token creado solo admite la operacion de consulta.
        $token = $user->createToken('postman-clase', ['personas:leer']);

        return response()->json([
            'token_type'   => 'Bearer',
            'access_token' => $token->plainTextToken,
        ], 200);
    }

    /**
     * Retorna los datos del usuario autenticado actualmente.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user(), 200);
    }

    /**
     * Revoca exclusivamente el token Bearer actual,
     * usado para peticiones autenticadas mediante token personal.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesion cerrada'], 200);
    }
}
