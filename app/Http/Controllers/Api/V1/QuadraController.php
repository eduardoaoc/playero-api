<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quadra\StoreQuadraRequest;
use App\Http\Requests\Quadra\ToggleQuadraStatusRequest;
use App\Http\Requests\Quadra\UpdateQuadraRequest;
use App\Models\Quadra;
use App\Support\ActivityLogger;
use App\Support\ApiResponse;
use App\Support\QuadraPresenter;
use OpenApi\Annotations as OA;

class QuadraController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/quadras",
     *     tags={"Quadras"},
     *     summary="Listar quadras ativas",
     *     @OA\Response(response=200, description="Lista de quadras ativas")
     * )
     */
    public function index()
    {
        $quadras = Quadra::active()->ordered()->get();

        return $this->successResponse(
            $quadras->map(fn (Quadra $quadra) => QuadraPresenter::make($quadra))->all(),
            'Quadras listadas com sucesso.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quadras",
     *     tags={"Quadras"},
     *     summary="Criar quadra",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nome","tipo"},
     *             @OA\Property(property="nome", type="string", example="Quadra 1"),
     *             @OA\Property(property="tipo", type="string", example="beach_tennis"),
     *             @OA\Property(property="ativa", type="boolean", example=true),
     *             @OA\Property(property="ordem", type="integer", example=1),
     *             @OA\Property(property="capacidade", type="integer", example=4)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Quadra criada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function store(StoreQuadraRequest $request)
    {
        $quadra = Quadra::create($request->validated());

        return $this->successResponse(
            QuadraPresenter::make($quadra),
            'Quadra criada com sucesso.',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/quadras/{id}",
     *     tags={"Quadras"},
     *     summary="Editar quadra",
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
     *             @OA\Property(property="nome", type="string", example="Quadra 1"),
     *             @OA\Property(property="tipo", type="string", example="beach_tennis"),
     *             @OA\Property(property="ordem", type="integer", example=1),
     *             @OA\Property(property="capacidade", type="integer", example=4)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Quadra atualizada"),
     *     @OA\Response(response=404, description="Quadra nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function update(UpdateQuadraRequest $request, int $id)
    {
        $quadra = Quadra::find($id);
        if (! $quadra) {
            return $this->errorResponse('Quadra nao encontrada.', 404);
        }

        $quadra->fill($request->validated());
        $quadra->save();

        return $this->successResponse(
            QuadraPresenter::make($quadra),
            'Quadra atualizada com sucesso.'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/quadras/{id}/status",
     *     tags={"Quadras"},
     *     summary="Ativar ou desativar quadra",
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
     *             required={"ativa"},
     *             @OA\Property(property="ativa", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status atualizado"),
     *     @OA\Response(response=404, description="Quadra nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function toggleStatus(ToggleQuadraStatusRequest $request, int $id)
    {
        $quadra = Quadra::find($id);
        if (! $quadra) {
            return $this->errorResponse('Quadra nao encontrada.', 404);
        }

        $data = $request->validated();
        $previous = (bool) $quadra->ativa;
        $quadra->ativa = $data['ativa'];
        $quadra->save();

        ActivityLogger::log(
            $request,
            'quadra_status_updated',
            'Status da quadra atualizado.',
            $quadra,
            [
                'quadra_id' => $quadra->id,
                'before' => $previous,
                'after' => (bool) $quadra->ativa,
            ]
        );

        return $this->successResponse(
            QuadraPresenter::make($quadra),
            'Status atualizado com sucesso.'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/quadras/{id}",
     *     tags={"Quadras"},
     *     summary="Excluir quadra",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Quadra removida"),
     *     @OA\Response(response=404, description="Quadra nao encontrada")
     * )
     */
    public function destroy(int $id)
    {
        $quadra = Quadra::find($id);
        if (! $quadra) {
            return $this->errorResponse('Quadra nao encontrada.', 404);
        }

        $quadra->delete();

        return $this->successResponse(null, 'Quadra removida com sucesso.');
    }
}
