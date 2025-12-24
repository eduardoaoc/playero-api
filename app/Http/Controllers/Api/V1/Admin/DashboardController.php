<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quadra;
use App\Models\Reserva;
use App\Models\User;
use App\Support\ApiResponse;
use OpenApi\Annotations as OA;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/admin/dashboard",
     *     tags={"Dashboard"},
     *     summary="Metricas do dashboard",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Metricas carregadas",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard carregado com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="reservas_total", type="integer", example=120),
     *                 @OA\Property(property="reservas_pendentes", type="integer", example=12),
     *                 @OA\Property(property="reservas_confirmadas", type="integer", example=90),
     *                 @OA\Property(property="quadras_ativas", type="integer", example=8),
     *                 @OA\Property(property="usuarios_ativos", type="integer", example=350)
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $data = [
            'reservas_total' => Reserva::query()->count(),
            'reservas_pendentes' => Reserva::query()
                ->where('status', Reserva::STATUS_PENDENTE_PAGAMENTO)
                ->count(),
            'reservas_confirmadas' => Reserva::query()
                ->where('status', Reserva::STATUS_CONFIRMADA)
                ->count(),
            'quadras_ativas' => Quadra::query()->where('ativa', true)->count(),
            'usuarios_ativos' => User::query()->where('is_active', true)->count(),
        ];

        return $this->successResponse($data, 'Dashboard carregado com sucesso.');
    }
}
