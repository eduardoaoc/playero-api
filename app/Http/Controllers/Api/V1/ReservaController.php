<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reserva\DisponibilidadeRequest;
use App\Http\Requests\Reserva\ListMinhasReservasRequest;
use App\Http\Requests\Reserva\ListReservasRequest;
use App\Http\Requests\Reserva\StoreReservaRequest;
use App\Models\AgendaBlocking;
use App\Models\AgendaException;
use App\Models\AgendaSetting;
use App\Models\Quadra;
use App\Models\Role;
use App\Models\Reserva;
use App\Support\ActivityLogger;
use App\Support\ApiResponse;
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

        $setting = AgendaSetting::query()->first();
        if (! $setting) {
            return $this->errorResponse('Configuracao da agenda nao encontrada.', 404);
        }

        $timezone = $this->resolveTimezone();
        $date = $data['data'];
        $quadraId = (int) $data['quadra_id'];
        $duration = (int) $setting->duracao_reserva_minutos;

        $workingHours = $this->resolveWorkingHours($date, $setting);
        $open = $this->buildDateTime($date, $workingHours['hora_abertura'], $timezone);
        $close = $this->buildDateTime($date, $workingHours['hora_fechamento'], $timezone);
        $now = Carbon::now($timezone);

        $activeDays = $this->normalizeWeekdays($setting->dias_semana_ativos ?? []);
        $weekday = $open->isoWeekday();

        if ($open->toDateString() < $now->toDateString()) {
            return $this->successResponse([
                'quadra_id' => $quadraId,
                'data' => $date,
                'timezone' => $timezone,
                'duracao_reserva_minutos' => $duration,
                'horarios' => [],
            ], 'Horarios disponiveis listados com sucesso.');
        }

        if (! $workingHours['is_exception'] && ! in_array($weekday, $activeDays, true)) {
            return $this->successResponse([
                'quadra_id' => $quadraId,
                'data' => $date,
                'timezone' => $timezone,
                'duracao_reserva_minutos' => $duration,
                'horarios' => [],
            ], 'Horarios disponiveis listados com sucesso.');
        }

        $blockings = AgendaBlocking::query()
            ->where('data', $date)
            ->where(function ($query) use ($quadraId) {
                $query->whereNull('quadra_id')->orWhere('quadra_id', $quadraId);
            })
            ->get();

        $reservas = Reserva::query()
            ->where('data', $date)
            ->where('quadra_id', $quadraId)
            ->whereIn('status', Reserva::ACTIVE_STATUSES)
            ->get(['hora_inicio', 'hora_fim']);

        $slots = [];

        for ($slotStart = $open->copy(); $slotStart->lt($close); $slotStart->addMinutes($duration)) {
            $slotEnd = $slotStart->copy()->addMinutes($duration);

            if ($slotEnd->gt($close)) {
                break;
            }

            if ($slotStart->lt($now)) {
                continue;
            }

            if ($this->isBlocked($slotStart, $slotEnd, $blockings, $date, $timezone)) {
                continue;
            }

            if ($this->hasReservaConflito($slotStart, $slotEnd, $reservas, $date, $timezone)) {
                continue;
            }

            $slots[] = [
                'hora_inicio' => $slotStart->format('H:i'),
                'hora_fim' => $slotEnd->format('H:i'),
            ];
        }

        return $this->successResponse([
            'quadra_id' => $quadraId,
            'data' => $date,
            'timezone' => $timezone,
            'duracao_reserva_minutos' => $duration,
            'horarios' => $slots,
        ], 'Horarios disponiveis listados com sucesso.');
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
     *             @OA\Property(property="hora_inicio", type="string", example="10:00")
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
     *     @OA\Response(response=409, description="Horario indisponivel"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function store(StoreReservaRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $this->expirePendentes();

        $setting = AgendaSetting::query()->first();
        if (! $setting) {
            return $this->errorResponse('Configuracao da agenda nao encontrada.', 404);
        }

        $timezone = $this->resolveTimezone();
        $date = $data['data'];
        $quadraId = (int) $data['quadra_id'];
        $duration = (int) $setting->duracao_reserva_minutos;

        $start = $this->buildDateTime($date, $data['hora_inicio'], $timezone);
        $end = $start->copy()->addMinutes($duration);
        $workingHours = $this->resolveWorkingHours($date, $setting);
        $open = $this->buildDateTime($date, $workingHours['hora_abertura'], $timezone);
        $close = $this->buildDateTime($date, $workingHours['hora_fechamento'], $timezone);

        $now = Carbon::now($timezone);
        $activeDays = $this->normalizeWeekdays($setting->dias_semana_ativos ?? []);

        if ($start->lt($now)) {
            return $this->errorResponse('Horario no passado.', 422);
        }

        if (! $workingHours['is_exception'] && ! in_array($start->isoWeekday(), $activeDays, true)) {
            return $this->errorResponse('Data fora dos dias ativos da agenda.', 422);
        }

        if ($start->lt($open) || $end->gt($close)) {
            return $this->errorResponse('Horario fora do intervalo da agenda.', 422);
        }

        $diff = $open->diffInMinutes($start);
        if ($diff % $duration !== 0) {
            return $this->errorResponse('Horario deve respeitar a duracao padrao da reserva.', 422);
        }

        $horaInicioDb = $start->format('H:i:s');
        $horaFimDb = $end->format('H:i:s');

        return DB::transaction(function () use (
            $quadraId,
            $date,
            $horaInicioDb,
            $horaFimDb,
            $user,
            $start,
            $end,
            $timezone
        ) {
            $quadra = Quadra::query()->where('id', $quadraId)->lockForUpdate()->first();

            if (! $quadra || ! $quadra->ativa) {
                return $this->errorResponse('Quadra nao encontrada ou inativa.', 422);
            }

            $blockings = AgendaBlocking::query()
                ->where('data', $date)
                ->where(function ($query) use ($quadraId) {
                    $query->whereNull('quadra_id')->orWhere('quadra_id', $quadraId);
                })
                ->get();

            if ($this->isBlocked($start, $end, $blockings, $date, $timezone)) {
                return $this->errorResponse('Horario bloqueado na agenda.', 409);
            }

            $conflict = Reserva::query()
                ->where('quadra_id', $quadraId)
                ->where('data', $date)
                ->whereIn('status', Reserva::ACTIVE_STATUSES)
                ->where('hora_inicio', '<', $horaFimDb)
                ->where('hora_fim', '>', $horaInicioDb)
                ->exists();

            if ($conflict) {
                return $this->errorResponse('Horario indisponivel.', 409);
            }

            $reserva = Reserva::create([
                'user_id' => $user->id,
                'quadra_id' => $quadraId,
                'data' => $date,
                'hora_inicio' => $horaInicioDb,
                'hora_fim' => $horaFimDb,
                'status' => Reserva::STATUS_PENDENTE_PAGAMENTO,
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

    private function resolveTimezone(): string
    {
        return self::DEFAULT_TIMEZONE;
    }

    private function expirePendentes(): void
    {
        Reserva::expirePendentes(Carbon::now(self::DEFAULT_TIMEZONE));
    }

    private function buildDateTime(string $date, string $time, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, $timezone);
    }

    private function normalizeWeekdays(array $days): array
    {
        return array_values(array_map('intval', $days));
    }

    private function resolveWorkingHours(string $date, AgendaSetting $setting): array
    {
        $exception = AgendaException::query()
            ->where('data', $date)
            ->first();

        if ($exception) {
            return [
                'hora_abertura' => $exception->hora_abertura,
                'hora_fechamento' => $exception->hora_fechamento,
                'is_exception' => true,
            ];
        }

        return [
            'hora_abertura' => $setting->hora_abertura,
            'hora_fechamento' => $setting->hora_fechamento,
            'is_exception' => false,
        ];
    }

    private function isBlocked(
        Carbon $start,
        Carbon $end,
        $blockings,
        string $date,
        string $timezone
    ): bool {
        foreach ($blockings as $blocking) {
            if ($blocking->hora_inicio === null && $blocking->hora_fim === null) {
                return true;
            }

            $blockStart = $this->buildDateTime($date, $blocking->hora_inicio, $timezone);
            $blockEnd = $this->buildDateTime($date, $blocking->hora_fim, $timezone);

            if ($this->intervalOverlaps($start, $end, $blockStart, $blockEnd)) {
                return true;
            }
        }

        return false;
    }

    private function hasReservaConflito(
        Carbon $start,
        Carbon $end,
        $reservas,
        string $date,
        string $timezone
    ): bool {
        foreach ($reservas as $reserva) {
            $reservaStart = $this->buildDateTime($date, $reserva->hora_inicio, $timezone);
            $reservaEnd = $this->buildDateTime($date, $reserva->hora_fim, $timezone);

            if ($this->intervalOverlaps($start, $end, $reservaStart, $reservaEnd)) {
                return true;
            }
        }

        return false;
    }

    private function intervalOverlaps(
        Carbon $start,
        Carbon $end,
        Carbon $otherStart,
        Carbon $otherEnd
    ): bool {
        return $start->lt($otherEnd) && $end->gt($otherStart);
    }
}
