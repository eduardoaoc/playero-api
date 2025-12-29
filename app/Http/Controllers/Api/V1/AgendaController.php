<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\StoreAgendaBlockingRequest;
use App\Http\Requests\Agenda\StoreAgendaExceptionRequest;
use App\Http\Requests\Agenda\UpdateAgendaConfigRequest;
use App\Http\Requests\Agenda\UpdateAgendaExceptionRequest;
use App\Models\AgendaBlocking;
use App\Models\AgendaException;
use App\Models\AgendaSetting;
use App\Support\AgendaBlockingPresenter;
use App\Support\AgendaExceptionPresenter;
use App\Support\AgendaSettingPresenter;
use App\Support\ApiResponse;
use OpenApi\Annotations as OA;

class AgendaController extends Controller
{
    use ApiResponse;

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
     *                 @OA\Property(property="duracao_reserva_minutos", type="integer", example=60),
     *                 @OA\Property(
     *                     property="dias_semana_ativos",
     *                     type="array",
     *                     @OA\Items(type="integer", example=1)
     *                 ),
     *                 @OA\Property(property="timezone", type="string", example="America/Sao_Paulo")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Configuracao nao encontrada")
     * )
     */
    public function getConfig()
    {
        $setting = AgendaSetting::query()->first();

        if (! $setting) {
            return $this->errorResponse('Configuracao da agenda nao encontrada.', 404);
        }

        return $this->successResponse(
            AgendaSettingPresenter::make($setting),
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
     *             required={"hora_abertura","hora_fechamento","duracao_reserva_minutos","dias_semana_ativos"},
     *             @OA\Property(property="hora_abertura", type="string", example="08:00"),
     *             @OA\Property(property="hora_fechamento", type="string", example="22:00"),
     *             @OA\Property(property="duracao_reserva_minutos", type="integer", example=60),
     *             @OA\Property(
     *                 property="dias_semana_ativos",
     *                 type="array",
     *                 @OA\Items(type="integer", example=1)
     *             ),
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
     *                 @OA\Property(property="duracao_reserva_minutos", type="integer", example=60),
     *                 @OA\Property(
     *                     property="dias_semana_ativos",
     *                     type="array",
     *                     @OA\Items(type="integer", example=1)
     *                 ),
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
     *                 @OA\Property(property="duracao_reserva_minutos", type="integer", example=60),
     *                 @OA\Property(
     *                     property="dias_semana_ativos",
     *                     type="array",
     *                     @OA\Items(type="integer", example=1)
     *                 ),
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
        $data['dias_semana_ativos'] = array_values(array_map('intval', $data['dias_semana_ativos']));

        $setting = AgendaSetting::query()->first();
        $created = false;

        if ($setting) {
            $setting->fill($data);
            $setting->save();
        } else {
            $setting = AgendaSetting::create($data);
            $created = true;
        }

        AgendaSetting::query()
            ->where('id', '!=', $setting->id)
            ->delete();

        return $this->successResponse(
            AgendaSettingPresenter::make($setting),
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
     *                     @OA\Property(property="hora_abertura", type="string", example="10:00"),
     *                     @OA\Property(property="hora_fechamento", type="string", example="18:00"),
     *                     @OA\Property(property="motivo", type="string", nullable=true, example="Evento interno")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listExceptions()
    {
        $exceptions = AgendaException::query()
            ->orderBy('data')
            ->orderBy('id')
            ->get();

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
     *             required={"data","hora_abertura","hora_fechamento"},
     *             @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *             @OA\Property(property="hora_abertura", type="string", example="10:00"),
     *             @OA\Property(property="hora_fechamento", type="string", example="18:00"),
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
     *                 @OA\Property(property="hora_abertura", type="string", example="10:00"),
     *                 @OA\Property(property="hora_fechamento", type="string", example="18:00"),
     *                 @OA\Property(property="motivo", type="string", nullable=true, example="Evento interno")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function storeException(StoreAgendaExceptionRequest $request)
    {
        $exception = AgendaException::create($request->validated());

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
     *             required={"data","hora_abertura","hora_fechamento"},
     *             @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *             @OA\Property(property="hora_abertura", type="string", example="10:00"),
     *             @OA\Property(property="hora_fechamento", type="string", example="18:00"),
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
     *                 @OA\Property(property="hora_abertura", type="string", example="10:00"),
     *                 @OA\Property(property="hora_fechamento", type="string", example="18:00"),
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

        $exception->fill($request->validated());
        $exception->save();

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

        $exception->delete();

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
        $blocking = AgendaBlocking::create($request->validated());

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
        $blockings = AgendaBlocking::query()
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->get();

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

        $blocking->delete();

        return $this->successResponse(null, 'Bloqueio removido com sucesso.');
    }
}
