<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\UserPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Auth"},
     *     summary="Login",
     *     description="Autentica e retorna um token de acesso",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="admin@playero.com"),
     *             @OA\Property(property="password", type="string", example="12345678")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login realizado"),
     *     @OA\Response(response=401, description="Credenciais invalidas"),
     *     @OA\Response(response=403, description="Usuario inativo"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->errorResponse('Credenciais invalidas.', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Usuario inativo.', 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('playero-api')->plainTextToken;

        $user->load('roles');

        return $this->successResponse([
            'token' => $token,
            'user' => UserPresenter::make($user),
        ], 'Login realizado com sucesso.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Auth"},
     *     summary="Logout",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logout realizado")
     * )
     */
    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return $this->successResponse(null, 'Logout realizado com sucesso.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Auth"},
     *     summary="Usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Usuario autenticado")
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('roles');

        return $this->successResponse(UserPresenter::make($user), 'Usuario autenticado.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     tags={"Auth"},
     *     summary="Renovar token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Token renovado")
     * )
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $token = $user->createToken('playero-api')->plainTextToken;

        if ($current) {
            $current->delete();
        }

        $user->load('roles');

        return $this->successResponse([
            'token' => $token,
            'user' => UserPresenter::make($user),
        ], 'Token renovado com sucesso.');
    }
}
