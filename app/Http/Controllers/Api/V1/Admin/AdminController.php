<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\ApiResponse;
use App\Support\UserPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

class AdminController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/admin/admins",
     *     tags={"Administradores"},
     *     summary="Listar administradores",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de administradores")
     * )
     */
    public function index()
    {
        $admins = User::query()
            ->with('roles')
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN]);
            })
            ->orderBy('id')
            ->get();

        return $this->successResponse(
            $admins->map(fn (User $user) => UserPresenter::make($user))->all(),
            'Administradores listados com sucesso.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/admins",
     *     tags={"Administradores"},
     *     summary="Criar administrador",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="Admin"),
     *             @OA\Property(property="email", type="string", example="admin@playero.com"),
     *             @OA\Property(property="password", type="string", example="12345678"),
     *             @OA\Property(property="password_confirmation", type="string", example="12345678"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Administrador criado"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function store(Request $request)
    {
        if (! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();

        $role = Role::where('name', Role::ADMIN)->first();
        if (! $role) {
            return $this->errorResponse('Role nao encontrada.', 422);
        }

        $admin = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $admin->roles()->sync([$role->id]);
        $admin->load('roles');

        ActivityLogger::log(
            $request,
            'admin_created',
            'Administrador criado.',
            $admin,
            ['admin_id' => $admin->id]
        );

        return $this->successResponse(
            UserPresenter::make($admin),
            'Administrador criado com sucesso.',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/admins/{id}",
     *     tags={"Administradores"},
     *     summary="Editar administrador",
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
     *             @OA\Property(property="password", type="string", example="12345678"),
     *             @OA\Property(property="password_confirmation", type="string", example="12345678")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Administrador atualizado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function update(Request $request, int $id)
    {
        if (! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $admin = $this->findAdmin($id);
        if (! $admin) {
            return $this->errorResponse('Administrador nao encontrado.', 404);
        }

        if ($admin->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Super admin nao pode ser alterado.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();

        if (array_key_exists('name', $data)) {
            $admin->name = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $admin->email = $data['email'];
        }

        if (! empty($data['password'])) {
            $admin->password = Hash::make($data['password']);
        }

        $admin->save();
        $admin->load('roles');

        ActivityLogger::log(
            $request,
            'admin_updated',
            'Administrador atualizado.',
            $admin,
            ['admin_id' => $admin->id]
        );

        return $this->successResponse(
            UserPresenter::make($admin),
            'Administrador atualizado com sucesso.'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/admin/admins/{id}/status",
     *     tags={"Administradores"},
     *     summary="Ativar ou desativar administrador",
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
        if (! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $admin = $this->findAdmin($id);
        if (! $admin) {
            return $this->errorResponse('Administrador nao encontrado.', 404);
        }

        if ($admin->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Super admin nao pode ser desativado.', 403);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $data = $validator->validated();
        $previous = (bool) $admin->is_active;
        $admin->is_active = $data['is_active'];
        $admin->save();

        if (! $admin->is_active) {
            $admin->tokens()->delete();
        }

        $admin->load('roles');

        ActivityLogger::log(
            $request,
            'admin_status_updated',
            'Status do administrador atualizado.',
            $admin,
            [
                'admin_id' => $admin->id,
                'before' => $previous,
                'after' => (bool) $admin->is_active,
            ]
        );

        return $this->successResponse(
            UserPresenter::make($admin),
            'Status atualizado com sucesso.'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/admins/{id}",
     *     tags={"Administradores"},
     *     summary="Excluir administrador",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Administrador removido"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=404, description="Administrador nao encontrado")
     * )
     */
    public function destroy(Request $request, int $id)
    {
        if (! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $admin = $this->findAdmin($id);
        if (! $admin) {
            return $this->errorResponse('Administrador nao encontrado.', 404);
        }

        if ($admin->hasRole(Role::SUPER_ADMIN)) {
            return $this->errorResponse('Super admin nao pode ser removido.', 403);
        }

        $admin->tokens()->delete();
        $admin->delete();

        ActivityLogger::log(
            $request,
            'admin_deleted',
            'Administrador removido.',
            $admin,
            ['admin_id' => $admin->id]
        );

        return $this->successResponse(null, 'Administrador removido com sucesso.');
    }

    private function findAdmin(int $id): ?User
    {
        return User::with('roles')
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN]);
            })
            ->find($id);
    }
}
