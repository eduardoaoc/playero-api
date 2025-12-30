<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reserva\DisponibilidadeRequest;
use App\Http\Requests\Reserva\ListQuadrasDisponiveisRequest;
use App\Http\Requests\Reserva\ListMinhasReservasRequest;
use App\Http\Requests\Reserva\ListReservasRequest;
use App\Http\Requests\Reserva\StoreReservaRequest;
use App\Models\Quadra;
use App\Models\Role;
use App\Models\Reserva;
use App\Models\User;
use App\Services\AgendaService;
use App\Support\ActivityLogger;
use App\Support\ApiResponse;
use App\Support\QuadraPresenter;
use App\Support\ReservaPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class ReservaController extends Controller
{
    use ApiResponse;

    private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    public function __construct(private readonly AgendaService $agendaService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/disponibilidade",
     *     tags={"Reservas"},
     *     summary="Consultar horarios disponiveis",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="quadra_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="data",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-12-24")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Horarios disponiveis",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Horarios disponiveis listados com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="quadra_id", type="integer", example=1),
     *                 @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                 @OA\Property(property="timezone", type="string", example="America/Sao_Paulo"),
     *                 @OA\Property(property="duracao_reserva_minutos", type="integer", example=60),
     *                 @OA\Property(
     *                     property="horarios",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="hora_inicio", type="string", example="10:00"),
     *                         @OA\Property(property="hora_fim", type="string", example="11:00")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=404, description="Configuracao da agenda nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function disponibilidade(DisponibilidadeRequest $request)
    {
        $data = $request->validated();

        $this->expirePendentes();

        $config = $this->agendaService->getConfig();
        if (! $config) {
            return $this->errorResponse('Configuracao da agenda nao encontrada.', 404);
        }

        $date = $data['data'];
        $quadraId = (int) $data['quadra_id'];
        $timezone = $this->agendaService->getTimezone($config);
        $duration = (int) $config->slot_duration;

        try {
            $availability = $this->agendaService->getDayAvailability(
                Carbon::createFromFormat('Y-m-d', $date),
                $quadraId
            );
        } catch (\RuntimeException $exception) {
            $status = $exception->getMessage() === 'Configuracao da agenda nao encontrada.' ? 404 : 422;

            return $this->errorResponse($exception->getMessage(), $status);
        }

        $slots = collect($availability['slots'])
            ->filter(fn (array $slot) => $slot['available'])
            ->map(fn (array $slot) => [
                'hora_inicio' => $slot['start'],
                'hora_fim' => $slot['end'],
            ])
            ->values()
            ->all();

        return $this->successResponse([
            'quadra_id' => $quadraId,
            'data' => $date,
            'timezone' => $timezone,
            'duracao_reserva_minutos' => $duration,
            'horarios' => $slots,
        ], 'Horarios disponiveis listados com sucesso.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quadras/disponiveis",
     *     tags={"Quadras"},
     *     summary="Listar quadras disponiveis",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="data",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-12-24")
     *     ),
     *     @OA\Parameter(
     *         name="hora_inicio",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="10:00")
     *     ),
     *     @OA\Response(response=200, description="Quadras disponiveis"),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=404, description="Configuracao da agenda nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function quadrasDisponiveis(ListQuadrasDisponiveisRequest $request)
    {
        $data = $request->validated();

        $this->expirePendentes();

        $config = $this->agendaService->getConfig();
        if (! $config) {
            return $this->errorResponse('Configuracao da agenda nao encontrada.', 404);
        }

        $date = $data['data'];
        $horaInicio = $data['hora_inicio'] ?? null;
        $hasHoraInicio = $horaInicio !== null;
        $duration = (int) $config->slot_duration;

        if ($duration <= 0) {
            return $this->errorResponse('Duracao de reserva invalida.', 422);
        }

        $quadras = Quadra::active()->ordered()->get();
        if ($quadras->isEmpty()) {
            return $this->successResponse([], 'Quadras disponiveis listadas com sucesso.');
        }

        $disponiveis = [];

        foreach ($quadras as $quadra) {
            if ($hasHoraInicio) {
                try {
                    $slot = $this->agendaService->getSlotAvailability(
                        Carbon::createFromFormat('Y-m-d', $date),
                        $horaInicio,
                        $quadra->id
                    );
                } catch (\RuntimeException $exception) {
                    return $this->errorResponse($exception->getMessage(), 422);
                }

                if (! $slot['available']) {
                    continue;
                }

                $disponiveis[] = QuadraPresenter::make($quadra);
                continue;
            }

            try {
                $availability = $this->agendaService->getDayAvailability(
                    Carbon::createFromFormat('Y-m-d', $date),
                    $quadra->id
                );
            } catch (\RuntimeException $exception) {
                return $this->errorResponse($exception->getMessage(), 422);
            }

            $hasDisponibilidade = collect($availability['slots'])
                ->contains(fn (array $slot) => $slot['available']);

            if ($hasDisponibilidade) {
                $disponiveis[] = QuadraPresenter::make($quadra);
            }
        }

        return $this->successResponse($disponiveis, 'Quadras disponiveis listadas com sucesso.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/reservas",
     *     tags={"Reservas"},
     *     summary="Criar reserva",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quadra_id","data","hora_inicio"},
     *             @OA\Property(property="quadra_id", type="integer", example=1),
     *             @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *             @OA\Property(property="hora_inicio", type="string", example="10:00"),
     *             @OA\Property(property="horario", type="string", example="10:00"),
     *             @OA\Property(property="cliente", type="string", example="Joao Silva"),
     *             @OA\Property(property="cliente_id", type="integer", example=5),
     *             @OA\Property(property="forma_pagamento", type="string", example="PIX")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Reserva criada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reserva criada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="quadra_id", type="integer", example=1),
     *                 @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                 @OA\Property(property="hora_inicio", type="string", example="10:00"),
     *                 @OA\Property(property="hora_fim", type="string", example="11:00"),
     *                 @OA\Property(property="status", type="string", example="pendente_pagamento")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=404, description="Configuracao da agenda nao encontrada"),
     *     @OA\Response(response=422, description="Horario indisponivel ou dados invalidos")
     * )
     */
    public function store(StoreReservaRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $clienteId = array_key_exists('cliente_id', $data) ? (int) $data['cliente_id'] : null;
        $clienteNome = array_key_exists('cliente', $data) ? trim((string) $data['cliente']) : null;
        $formaPagamento = array_key_exists('forma_pagamento', $data) ? trim((string) $data['forma_pagamento']) : null;

        if ($clienteNome === '') {
            $clienteNome = null;
        }

        if ($formaPagamento === '') {
            $formaPagamento = null;
        }

        if ($formaPagamento === '---') {
            $formaPagamento = null;
        }

        if ($clienteId && $clienteId !== $user->id
            && ! $user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])) {
            return $this->errorResponse('Sem permissao.', 403);
        }

        $this->expirePendentes();

        $config = $this->agendaService->getConfig();
        if (! $config) {
            return $this->errorResponse('Configuracao da agenda nao encontrada.', 404);
        }

        $date = $data['data'];
        $quadraId = (int) $data['quadra_id'];
        $timezone = $this->agendaService->getTimezone($config);

        $clienteUser = null;
        if ($clienteId) {
            $clienteUser = User::query()->select(['id', 'name'])->find($clienteId);
            if (! $clienteUser) {
                return $this->errorResponse('Cliente nao encontrado.', 422);
            }
        }

        if (! $clienteNome && $clienteUser) {
            $clienteNome = $clienteUser->name;
        }

        $userId = $clienteUser ? $clienteUser->id : $user->id;

        try {
            $slotAvailability = $this->agendaService->getSlotAvailability(
                Carbon::createFromFormat('Y-m-d', $date),
                $data['hora_inicio'],
                $quadraId
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        if (! $slotAvailability['available']) {
            return $this->errorResponse(
                $this->mapSlotReasonToMessage($slotAvailability['reason']),
                422
            );
        }

        $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$data['hora_inicio'], $timezone);
        $end = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$slotAvailability['end_time'], $timezone);
        $horaInicioDb = $start->format('H:i:s');
        $horaFimDb = $end->format('H:i:s');

        return DB::transaction(function () use (
            $quadraId,
            $date,
            $horaInicioDb,
            $horaFimDb,
            $userId,
            $clienteNome,
            $formaPagamento,
            $start,
            $end,
            $timezone
        ) {
            $quadra = Quadra::query()->where('id', $quadraId)->lockForUpdate()->first();

            if (! $quadra || ! $quadra->ativa) {
                return $this->errorResponse('Quadra nao encontrada ou inativa.', 422);
            }

            if ($this->agendaService->hasReservationConflict($quadraId, $date, $horaInicioDb, $horaFimDb)) {
                return $this->errorResponse('Horario indisponivel.', 422);
            }

            $reserva = Reserva::create([
                'user_id' => $userId,
                'cliente_nome' => $clienteNome,
                'quadra_id' => $quadraId,
                'data' => $date,
                'hora_inicio' => $horaInicioDb,
                'hora_fim' => $horaFimDb,
                'status' => Reserva::STATUS_PENDENTE_PAGAMENTO,
                'forma_pagamento' => $formaPagamento,
            ]);

            return $this->successResponse(
                ReservaPresenter::make($reserva),
                'Reserva criada com sucesso.',
                201
            );
        });
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/reservas/{id}/cancelar",
     *     tags={"Reservas Admin"},
     *     summary="Cancelar reserva (admin)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reserva cancelada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reserva cancelada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="quadra_id", type="integer", example=1),
     *                 @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                 @OA\Property(property="hora_inicio", type="string", example="10:00"),
     *                 @OA\Property(property="hora_fim", type="string", example="11:00"),
     *                 @OA\Property(property="status", type="string", example="cancelada")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=404, description="Reserva nao encontrada"),
     *     @OA\Response(response=422, description="Reserva nao pode ser cancelada")
     * )
     *
     * @OA\Post(
     *     path="/api/v1/reservas/{id}/cancelar",
     *     tags={"Reservas"},
     *     summary="Cancelar reserva",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reserva cancelada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reserva cancelada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="quadra_id", type="integer", example=1),
     *                 @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                 @OA\Property(property="hora_inicio", type="string", example="10:00"),
     *                 @OA\Property(property="hora_fim", type="string", example="11:00"),
     *                 @OA\Property(property="status", type="string", example="cancelada")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=404, description="Reserva nao encontrada"),
     *     @OA\Response(response=422, description="Reserva nao pode ser cancelada")
     * )
     */
    public function cancel(Request $request, int $id)
    {
        $this->expirePendentes();

        $reserva = Reserva::find($id);

        if (! $reserva) {
            return $this->errorResponse('Reserva nao encontrada.', 404);
        }

        Gate::authorize('cancel', $reserva);

        if (in_array($reserva->status, [Reserva::STATUS_CANCELADA, Reserva::STATUS_EXPIRADA], true)) {
            return $this->errorResponse('Reserva nao pode ser cancelada.', 422);
        }

        $previous = $reserva->status;
        $reserva->status = Reserva::STATUS_CANCELADA;
        $reserva->save();

        ActivityLogger::log(
            $request,
            'reserva_cancelada',
            'Reserva cancelada.',
            $reserva,
            [
                'reserva_id' => $reserva->id,
                'before' => $previous,
                'after' => $reserva->status,
                'by_admin' => $request->user()->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN]),
            ]
        );

        return $this->successResponse(
            ReservaPresenter::make($reserva),
            'Reserva cancelada com sucesso.'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/reservas",
     *     tags={"Reservas Admin"},
     *     summary="Listar reservas (admin)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="data",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-24")
     *     ),
     *     @OA\Parameter(
     *         name="quadra_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="confirmada")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservas listadas",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reservas listadas com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=5),
     *                     @OA\Property(property="quadra_id", type="integer", example=1),
     *                     @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                     @OA\Property(property="hora_inicio", type="string", example="10:00"),
     *                     @OA\Property(property="hora_fim", type="string", example="11:00"),
     *                     @OA\Property(property="status", type="string", example="confirmada")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     *
     * @OA\Get(
     *     path="/api/v1/reservas",
     *     tags={"Reservas"},
     *     summary="Listar reservas (admin)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="data",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-24")
     *     ),
     *     @OA\Parameter(
     *         name="quadra_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="confirmada")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservas listadas",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reservas listadas com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=5),
     *                     @OA\Property(property="quadra_id", type="integer", example=1),
     *                     @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                     @OA\Property(property="hora_inicio", type="string", example="10:00"),
     *                     @OA\Property(property="hora_fim", type="string", example="11:00"),
     *                     @OA\Property(property="status", type="string", example="confirmada")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function index(ListReservasRequest $request)
    {
        $this->expirePendentes();

        Gate::authorize('viewAny', Reserva::class);

        $filters = $request->validated();

        $query = Reserva::query()
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->orderBy('id');

        if (! empty($filters['data'])) {
            $query->where('data', $filters['data']);
        }

        if (! empty($filters['quadra_id'])) {
            $query->where('quadra_id', $filters['quadra_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $reservas = $query->get();

        return $this->successResponse(
            $reservas->map(fn (Reserva $reserva) => ReservaPresenter::make($reserva))->all(),
            'Reservas listadas com sucesso.'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/minhas-reservas",
     *     tags={"Reservas"},
     *     summary="Listar minhas reservas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="data",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-24")
     *     ),
     *     @OA\Parameter(
     *         name="quadra_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="pendente_pagamento")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservas listadas",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reservas listadas com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=5),
     *                     @OA\Property(property="quadra_id", type="integer", example=1),
     *                     @OA\Property(property="data", type="string", format="date", example="2025-12-24"),
     *                     @OA\Property(property="hora_inicio", type="string", example="10:00"),
     *                     @OA\Property(property="hora_fim", type="string", example="11:00"),
     *                     @OA\Property(property="status", type="string", example="pendente_pagamento")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function minhasReservas(ListMinhasReservasRequest $request)
    {
        $this->expirePendentes();

        $filters = $request->validated();

        $query = Reserva::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->orderBy('id');

        if (! empty($filters['data'])) {
            $query->where('data', $filters['data']);
        }

        if (! empty($filters['quadra_id'])) {
            $query->where('quadra_id', $filters['quadra_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $reservas = $query->get();

        return $this->successResponse(
            $reservas->map(fn (Reserva $reserva) => ReservaPresenter::make($reserva))->all(),
            'Reservas listadas com sucesso.'
        );
    }

    private function expirePendentes(): void
    {
        Reserva::expirePendentes(Carbon::now(self::DEFAULT_TIMEZONE));
    }

    private function mapSlotReasonToMessage(?string $reason): string
    {
        return match ($reason) {
            'closed' => 'Data indisponivel: feriado/fechado.',
            'outside_hours' => 'Fora do horario de funcionamento.',
            'past' => 'Horario no passado.',
            'blocking' => 'Horario bloqueado.',
            'event' => 'Horario indisponivel: evento.',
            default => 'Horario indisponivel.',
        };
    }
}
