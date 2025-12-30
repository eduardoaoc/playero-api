<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\StoreAgendaBlockingRequest;
use App\Http\Requests\Agenda\StoreAgendaExceptionRequest;
use App\Http\Requests\Agenda\UpdateAgendaConfigRequest;
use App\Http\Requests\Agenda\UpdateAgendaExceptionRequest;
use App\Models\AgendaBlocking;
use App\Models\AgendaException;
use App\Support\AgendaBlockingPresenter;
use App\Support\AgendaExceptionPresenter;
use App\Support\AgendaConfigPresenter;
use App\Support\ApiResponse;
use App\Services\AgendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Annotations as OA;

class AgendaController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AgendaService $agendaService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/agenda/config",
     *     tags={"Agenda"},
     *     summary="Obter configuracao da agenda",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Configuracao atual",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Configuracao da agenda carregada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="hora_abertura", type="string", example="08:00"),
     *                 @OA\Property(property="hora_fechamento", type="string", example="22:00"),
     *                 @OA\Property(property="timezone", type="string", example="America/Sao_Paulo")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Configuracao nao encontrada")
     * )
     */
    public function getConfig()
    {
        $setting = $this->agendaService->getConfig();

        if (! $setting) {
            return $this->errorResponse('Configuracao da agenda nao encontrada.', 404);
        }

        return $this->successResponse(
            AgendaConfigPresenter::make($setting),
            'Configuracao da agenda carregada com sucesso.'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/agenda/config",
     *     tags={"Agenda"},
     *     summary="Criar ou atualizar configuracao da agenda",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"hora_abertura","hora_fechamento"},
     *             @OA\Property(property="hora_abertura", type="string", example="08:00"),
     *             @OA\Property(property="hora_fechamento", type="string", example="22:00"),
     *             @OA\Property(property="timezone", type="string", example="America/Sao_Paulo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Configuracao atualizada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Configuracao da agenda atualizada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="hora_abertura", type="string", example="08:00"),
     *                 @OA\Property(property="hora_fechamento", type="string", example="22:00"),
     *                 @OA\Property(property="timezone", type="string", example="America/Sao_Paulo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Configuracao criada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Configuracao da agenda criada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="hora_abertura", type="string", example="08:00"),
     *                 @OA\Property(property="hora_fechamento", type="string", example="22:00"),
     *                 @OA\Property(property="timezone", type="string", example="America/Sao_Paulo")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function updateConfig(UpdateAgendaConfigRequest $request)
    {
        $data = $request->validated();
        $setting = $this->agendaService->upsertConfig($data);
        $created = (bool) $setting->getAttribute('was_created');

        return $this->successResponse(
            AgendaConfigPresenter::make($setting),
            $created ? 'Configuracao da agenda criada com sucesso.' : 'Configuracao da agenda atualizada com sucesso.',
            $created ? 201 : 200
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/agenda/exceptions",
     *     tags={"Agenda"},
     *     summary="Listar excecoes de horario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de excecoes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Excecoes de horario listadas com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                     @OA\Property(property="hora_abertura", type="string", nullable=true, example="10:00"),
     *                     @OA\Property(property="hora_fechamento", type="string", nullable=true, example="18:00"),
     *                     @OA\Property(property="fechado", type="boolean", example=false),
     *                     @OA\Property(property="motivo", type="string", nullable=true, example="Evento interno")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listExceptions()
    {
        $exceptions = $this->agendaService->listExceptions();

        return $this->successResponse(
            $exceptions->map(fn (AgendaException $exception) => AgendaExceptionPresenter::make($exception))->all(),
            'Excecoes de horario listadas com sucesso.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/agenda/exceptions",
     *     tags={"Agenda"},
     *     summary="Criar excecao de horario",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *             @OA\Property(property="hora_abertura", type="string", nullable=true, example="10:00"),
     *             @OA\Property(property="hora_fechamento", type="string", nullable=true, example="18:00"),
     *             @OA\Property(property="fechado", type="boolean", example=false),
     *             @OA\Property(property="motivo", type="string", nullable=true, example="Evento interno")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Excecao criada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Excecao de horario criada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                 @OA\Property(property="hora_abertura", type="string", nullable=true, example="10:00"),
     *                 @OA\Property(property="hora_fechamento", type="string", nullable=true, example="18:00"),
     *                 @OA\Property(property="fechado", type="boolean", example=false),
     *                 @OA\Property(property="motivo", type="string", nullable=true, example="Evento interno")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function storeException(StoreAgendaExceptionRequest $request)
    {
        $exception = $this->agendaService->createException($request->validated());

        return $this->successResponse(
            AgendaExceptionPresenter::make($exception),
            'Excecao de horario criada com sucesso.',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/agenda/exceptions/{id}",
     *     tags={"Agenda"},
     *     summary="Atualizar excecao de horario",
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
     *             required={"data"},
     *             @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *             @OA\Property(property="hora_abertura", type="string", nullable=true, example="10:00"),
     *             @OA\Property(property="hora_fechamento", type="string", nullable=true, example="18:00"),
     *             @OA\Property(property="fechado", type="boolean", example=false),
     *             @OA\Property(property="motivo", type="string", nullable=true, example="Evento interno")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Excecao atualizada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Excecao de horario atualizada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                 @OA\Property(property="hora_abertura", type="string", nullable=true, example="10:00"),
     *                 @OA\Property(property="hora_fechamento", type="string", nullable=true, example="18:00"),
     *                 @OA\Property(property="fechado", type="boolean", example=false),
     *                 @OA\Property(property="motivo", type="string", nullable=true, example="Evento interno")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Excecao nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function updateException(UpdateAgendaExceptionRequest $request, int $id)
    {
        $exception = AgendaException::find($id);

        if (! $exception) {
            return $this->errorResponse('Excecao de horario nao encontrada.', 404);
        }

        $exception = $this->agendaService->updateException($exception, $request->validated());

        return $this->successResponse(
            AgendaExceptionPresenter::make($exception),
            'Excecao de horario atualizada com sucesso.'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/agenda/exceptions/{id}",
     *     tags={"Agenda"},
     *     summary="Excluir excecao de horario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Excecao removida",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Excecao de horario removida com sucesso."),
     *             @OA\Property(property="data", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Excecao nao encontrada")
     * )
     */
    public function deleteException(int $id)
    {
        $exception = AgendaException::find($id);

        if (! $exception) {
            return $this->errorResponse('Excecao de horario nao encontrada.', 404);
        }

        $this->agendaService->deleteException($exception);

        return $this->successResponse(null, 'Excecao de horario removida com sucesso.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/agenda/blockings",
     *     tags={"Agenda"},
     *     summary="Criar bloqueio manual",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="quadra_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *             @OA\Property(property="hora_inicio", type="string", nullable=true, example="10:00"),
     *             @OA\Property(property="hora_fim", type="string", nullable=true, example="12:00"),
     *             @OA\Property(property="motivo", type="string", nullable=true, example="Manutencao")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bloqueio criado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bloqueio criado com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="quadra_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                 @OA\Property(property="hora_inicio", type="string", nullable=true, example="10:00"),
     *                 @OA\Property(property="hora_fim", type="string", nullable=true, example="12:00"),
     *                 @OA\Property(property="motivo", type="string", nullable=true, example="Manutencao")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function storeBlocking(StoreAgendaBlockingRequest $request)
    {
        if (! $this->agendaService->getConfig()) {
            return $this->errorResponse('Configuracao da agenda nao encontrada.', 404);
        }

        try {
            $blocking = $this->agendaService->createBlocking($request->validated());
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        return $this->successResponse(
            AgendaBlockingPresenter::make($blocking),
            'Bloqueio criado com sucesso.',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/agenda/blockings",
     *     tags={"Agenda"},
     *     summary="Listar bloqueios",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de bloqueios",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bloqueios listados com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="quadra_id", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                     @OA\Property(property="hora_inicio", type="string", nullable=true, example="10:00"),
     *                     @OA\Property(property="hora_fim", type="string", nullable=true, example="12:00"),
     *                     @OA\Property(property="motivo", type="string", nullable=true, example="Manutencao")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listBlockings()
    {
        $blockings = $this->agendaService->listBlockings();

        return $this->successResponse(
            $blockings->map(fn (AgendaBlocking $blocking) => AgendaBlockingPresenter::make($blocking))->all(),
            'Bloqueios listados com sucesso.'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/agenda/blockings/{id}",
     *     tags={"Agenda"},
     *     summary="Excluir bloqueio",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bloqueio removido",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bloqueio removido com sucesso."),
     *             @OA\Property(property="data", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Bloqueio nao encontrado")
     * )
     */
    public function deleteBlocking(int $id)
    {
        $blocking = AgendaBlocking::find($id);

        if (! $blocking) {
            return $this->errorResponse('Bloqueio nao encontrado.', 404);
        }

        $this->agendaService->deleteBlocking($blocking);

        return $this->successResponse(null, 'Bloqueio removido com sucesso.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/agenda/day",
     *     tags={"Agenda"},
     *     summary="Consultar disponibilidade por dia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-01-15")
     *     ),
     *     @OA\Parameter(
     *         name="quadra_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Disponibilidade do dia",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Disponibilidade carregada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(property="is_closed", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="slots",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="start", type="string", example="08:00"),
     *                         @OA\Property(property="end", type="string", example="09:00"),
     *                         @OA\Property(property="available", type="boolean", example=true),
     *                         @OA\Property(property="reason", type="string", nullable=true, example=null)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Configuracao nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function dayAvailability(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'quadra_id' => ['nullable', 'integer', 'exists:quadras,id'],
        ]);

        try {
            $availability = $this->agendaService->getDayAvailability(
                Carbon::createFromFormat('Y-m-d', $validated['date']),
                $validated['quadra_id'] ?? null
            );
        } catch (\RuntimeException $exception) {
            $status = $exception->getMessage() === 'Configuracao da agenda nao encontrada.' ? 404 : 422;

            return $this->errorResponse($exception->getMessage(), $status);
        }

        return $this->successResponse($availability, 'Disponibilidade carregada com sucesso.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/agenda/month",
     *     tags={"Agenda"},
     *     summary="Consultar calendario mensal",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", example="2025-01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calendario do mes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Calendario carregado com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="date", type="string", format="date", example="2025-01-01"),
     *                     @OA\Property(property="type", type="string", example="available")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Configuracao nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function monthAvailability(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $availability = $this->agendaService->getMonthAvailability(
                Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            );
        } catch (\RuntimeException $exception) {
            $status = $exception->getMessage() === 'Configuracao da agenda nao encontrada.' ? 404 : 422;

            return $this->errorResponse($exception->getMessage(), $status);
        }

        return $this->successResponse($availability, 'Calendario carregado com sucesso.');
    }
}
