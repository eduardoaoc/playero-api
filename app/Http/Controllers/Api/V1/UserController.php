<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\UserPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     tags={"Users"},
     *     summary="Listar usuarios",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de usuarios")
     * )
     */
    public function index()
    {
        $users = User::with('roles')->orderBy('id')->get();

        return $this->successResponse(
            $users->map(fn (User $user) => UserPresenter::make($user))->all(),
            'Usuarios listados com sucesso.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     tags={"Users"},
     *     summary="Criar usuario",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation","role"},
     *             @OA\Property(property="name", type="string", example="Admin"),
     *             @OA\Property(property="email", type="string", example="admin@playero.com"),
     *             @OA\Property(property="password", type="string", example="12345678"),
     *             @OA\Property(property="password_confirmation", type="string", example="12345678"),
     *             @OA\Property(property="role", type="string", example="ADMIN"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario criado"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(Role::ALL)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();

        if (in_array($data['role'], [Role::SUPER_ADMIN, Role::ADMIN], true)
            && ! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        if ($data['role'] === Role::SUPER_ADMIN
            && array_key_exists('is_active', $data)
            && ! $data['is_active']) {
            return $this->errorResponse('Super admin nao pode ser desativado.', 403);
        }

        $role = Role::where('name', $data['role'])->first();
        if (! $role) {
            return $this->errorResponse('Role nao encontrado.', 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->roles()->sync([$role->id]);

        $user->load('roles');

        return $this->successResponse(
            UserPresenter::make($user),
            'Usuario criado com sucesso.',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Editar usuario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Admin"),
     *             @OA\Property(property="email", type="string", example="admin@playero.com"),
     *             @OA\Property(property="role", type="string", example="ADMIN")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Usuario atualizado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function update(Request $request, int $id)
    {
        $user = $this->findUser($id);
        if (! $user) {
            return $this->errorResponse('Usuario nao encontrado.', 404);
        }

        if ($user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])
            && ! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'string', Rule::in(Role::ALL)],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }

        if (array_key_exists('role', $data)) {
            if (in_array($data['role'], [Role::SUPER_ADMIN, Role::ADMIN], true)
                && ! $request->user()->hasRole(Role::SUPER_ADMIN)) {
                return $this->errorResponse('Sem permissao.', 403);
            }

            $role = Role::where('name', $data['role'])->first();
            if (! $role) {
                return $this->errorResponse('Role nao encontrado.', 422);
            }

            $user->roles()->sync([$role->id]);
        }

        $user->save();
        $user->load('roles');

        return $this->successResponse(UserPresenter::make($user), 'Usuario atualizado com sucesso.');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/users/{id}/status",
     *     tags={"Users"},
     *     summary="Ativar ou desativar usuario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_active"},
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status atualizado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function updateStatus(Request $request, int $id)
    {
        $user = $this->findUser($id);
        if (! $user) {
            return $this->errorResponse('Usuario nao encontrado.', 404);
        }

        if ($user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])
            && ! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();

        if ($user->hasRole(Role::SUPER_ADMIN) && ! $data['is_active']) {
            return $this->errorResponse('Super admin nao pode ser desativado.', 403);
        }

        $user->is_active = $data['is_active'];
        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        $user->load('roles');

        return $this->successResponse(UserPresenter::make($user), 'Status atualizado com sucesso.');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}/password",
     *     tags={"Users"},
     *     summary="Atualizar senha do usuario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password","password_confirmation"},
     *             @OA\Property(property="password", type="string", example="12345678"),
     *             @OA\Property(property="password_confirmation", type="string", example="12345678")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Senha atualizada"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function updatePassword(Request $request, int $id)
    {
        $user = $this->findUser($id);
        if (! $user) {
            return $this->errorResponse('Usuario nao encontrado.', 404);
        }

        if ($user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])
            && ! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();
        $user->password = Hash::make($data['password']);
        $user->save();

        $user->tokens()->delete();

        return $this->successResponse(null, 'Senha atualizada com sucesso.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/reset-password",
     *     tags={"Users"},
     *     summary="Redefinir senha pelo email",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","password_confirmation"},
     *             @OA\Property(property="email", type="string", example="admin@playero.com"),
     *             @OA\Property(property="password", type="string", example="12345678"),
     *             @OA\Property(property="password_confirmation", type="string", example="12345678")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Senha redefinida"),
     *     @OA\Response(response=404, description="Usuario nao encontrado"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return $this->errorResponse('Usuario nao encontrado.', 404);
        }

        if ($user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])
            && ! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        $user->tokens()->delete();

        return $this->successResponse(null, 'Senha redefinida com sucesso.');
    }

    private function findUser(int $id): ?User
    {
        return User::with('roles')->find($id);
    }
}
