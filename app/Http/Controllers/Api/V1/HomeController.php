<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CmsSection;
use App\Support\ApiResponse;
use App\Support\CmsSectionPresenter;
use OpenApi\Annotations as OA;

class HomeController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/home",
     *     tags={"CMS Home"},
     *     summary="Conteudo da Home",
     *     @OA\Response(
     *         response=200,
     *         description="Conteudo carregado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Home carregada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="sections",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="section", type="string", example="banner_principal"),
     *                         @OA\Property(property="order", type="integer", example=1),
     *                         @OA\Property(property="active", type="boolean", example=true),
     *                         @OA\Property(
     *                             property="fields",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=10),
     *                                 @OA\Property(property="key", type="string", example="titulo"),
     *                                 @OA\Property(property="type", type="string", example="text"),
     *                                 @OA\Property(property="value", type="string", example="Bem-vindo"),
     *                                 @OA\Property(property="order", type="integer", example=1),
     *                                 @OA\Property(property="active", type="boolean", example=true)
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function show()
    {
        $sections = CmsSection::query()
            ->where('active', true)
            ->with(['fields' => function ($query) {
                $query->where('active', true)
                    ->orderBy('order')
                    ->orderBy('id')
                    ->with('media');
            }])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return $this->successResponse([
            'sections' => $sections->map(fn (CmsSection $section) => CmsSectionPresenter::make($section))->all(),
        ], 'Home carregada com sucesso.');
    }
}
