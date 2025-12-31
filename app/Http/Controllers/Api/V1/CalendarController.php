<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\StoreAgendaExceptionRequest;
use App\Http\Requests\Agenda\UpdateAgendaExceptionRequest;
use App\Models\AgendaException;
use App\Services\AgendaService;
use App\Services\CalendarService;
use App\Support\ApiResponse;
use App\Support\CalendarExceptionPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

class CalendarController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AgendaService $agendaService,
        private readonly CalendarService $calendarService
    )
    {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/calendar/overview",
     *     tags={"Calendario"},
     *     summary="Consultar calendario geral do mes",
     *     description="Consolida reservas, eventos, bloqueios e excecoes para o mes informado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer", example=2, minimum=1, maximum=12)
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calendario carregado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Calendario geral carregado com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="month", type="integer", example=12),
     *                 @OA\Property(property="year", type="integer", example=2025),
     *                 @OA\Property(
     *                     property="days",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="date", type="string", format="date", example="2025-12-05"),
     *                         @OA\Property(
     *                             property="reservations",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=10),
     *                                 @OA\Property(property="quadra", type="string", example="Quadra 01"),
     *                                 @OA\Property(property="start_time", type="string", example="18:00"),
     *                                 @OA\Property(property="end_time", type="string", example="19:00"),
     *                                 @OA\Property(property="status", type="string", example="CONFIRMADA"),
     *                                 @OA\Property(property="payment_method", type="string", example="PIX"),
     *                                 @OA\Property(property="value", type="number", example=0)
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="events",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=3),
     *                                 @OA\Property(property="title", type="string", example="Aniversario Infantil"),
     *                                 @OA\Property(property="type", type="string", example="privado"),
     *                                 @OA\Property(property="start", type="string", example="15:00"),
     *                                 @OA\Property(property="end", type="string", example="18:00")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="blockings",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="reason", type="string", example="Natal")
     *                             )
     *                         ),
     *                         @OA\Property(property="closed_all_day", type="boolean", example=false)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=404, description="Configuracao nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function overview(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'digits:4'],
        ]);

        try {
            $overview = $this->calendarService->getOverview(
                (int) $validated['year'],
                (int) $validated['month']
            );
        } catch (\RuntimeException $exception) {
            $status = $exception->getMessage() === 'Configuracao da agenda nao encontrada.' ? 404 : 422;

            return $this->errorResponse($exception->getMessage(), $status);
        }

        $response = [
            'month' => (int) $validated['month'],
            'year' => (int) $validated['year'],
            'days' => $overview,
        ];

        Log::info('Calendar overview payload', $response);

        return $this->successResponse($response, 'Calendario geral carregado com sucesso.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/calendar/day/{date}",
     *     tags={"Calendario"},
     *     summary="Consultar detalhe de um dia",
     *     description="Retorna reservas, eventos e bloqueios do dia informado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="date",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-02-10")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalhe carregado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detalhe do dia carregado com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="date", type="string", format="date", example="2025-02-10"),
     *                 @OA\Property(property="status", type="string", example="open"),
     *                 @OA\Property(property="is_closed", type="boolean", example=false),
     *                 @OA\Property(property="is_holiday", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="special_hours",
     *                     nullable=true,
     *                     type="object",
     *                     @OA\Property(property="open_time", type="string", example="10:00"),
     *                     @OA\Property(property="close_time", type="string", example="16:00")
     *                 ),
     *                 @OA\Property(property="reason", type="string", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="reservations",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=12),
     *                         @OA\Property(property="quadra", type="string", example="Quadra 01"),
     *                         @OA\Property(property="start_time", type="string", example="18:00"),
     *                         @OA\Property(property="end_time", type="string", example="19:00"),
     *                         @OA\Property(property="client_name", type="string", example="Joao Silva"),
     *                         @OA\Property(property="status", type="string", example="confirmada"),
     *                         @OA\Property(property="payment_status", type="string", example="pago")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="events",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="title", type="string", example="Aniversario Infantil"),
     *                         @OA\Property(property="type", type="string", example="aniversario"),
     *                         @OA\Property(property="start_time", type="string", example="15:00"),
     *                         @OA\Property(property="end_time", type="string", example="18:00"),
     *                         @OA\Property(property="location", type="string", example="Area Kids")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="blockings",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=7),
     *                         @OA\Property(property="start_time", type="string", example="08:00"),
     *                         @OA\Property(property="end_time", type="string", example="10:00"),
     *                         @OA\Property(property="reason", type="string", example="Manutencao"),
     *                         @OA\Property(property="quadra_id", type="integer", nullable=true, example=null)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=404, description="Configuracao nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function dayDetail(Request $request, string $date)
    {
        $request->merge(['date' => $date]);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $detail = $this->agendaService->getCalendarDayDetail(
                Carbon::createFromFormat('Y-m-d', $validated['date'])
            );
        } catch (\RuntimeException $exception) {
            $status = $exception->getMessage() === 'Configuracao da agenda nao encontrada.' ? 404 : 422;

            return $this->errorResponse($exception->getMessage(), $status);
        }

        return $this->successResponse($detail, 'Detalhe do dia carregado com sucesso.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/calendar/exceptions",
     *     tags={"Calendario"},
     *     summary="Criar excecao de calendario",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"date"},
     *             @OA\Property(property="date", type="string", format="date", example="2025-12-25"),
     *             @OA\Property(property="is_closed", type="boolean", example=true),
     *             @OA\Property(property="open_time", type="string", nullable=true, example="10:00"),
     *             @OA\Property(property="close_time", type="string", nullable=true, example="16:00"),
     *             @OA\Property(property="reason", type="string", nullable=true, example="Natal")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Excecao criada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Excecao de calendario criada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="date", type="string", format="date", example="2025-12-25"),
     *                 @OA\Property(property="is_closed", type="boolean", example=true),
     *                 @OA\Property(property="open_time", type="string", nullable=true, example=null),
     *                 @OA\Property(property="close_time", type="string", nullable=true, example=null),
     *                 @OA\Property(property="reason", type="string", nullable=true, example="Natal")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function storeException(StoreAgendaExceptionRequest $request)
    {
        $payload = $request->validated();
        $payload['created_by'] = $request->user()->id;

        $exception = $this->agendaService->createException($payload);

        return $this->successResponse(
            CalendarExceptionPresenter::make($exception),
            'Excecao de calendario criada com sucesso.',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/calendar/exceptions/{id}",
     *     tags={"Calendario"},
     *     summary="Atualizar excecao de calendario",
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
     *             required={"date"},
     *             @OA\Property(property="date", type="string", format="date", example="2025-12-31"),
     *             @OA\Property(property="is_closed", type="boolean", example=false),
     *             @OA\Property(property="open_time", type="string", nullable=true, example="10:00"),
     *             @OA\Property(property="close_time", type="string", nullable=true, example="16:00"),
     *             @OA\Property(property="reason", type="string", nullable=true, example="Vespera de ano novo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Excecao atualizada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Excecao de calendario atualizada com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="date", type="string", format="date", example="2025-12-31"),
     *                 @OA\Property(property="is_closed", type="boolean", example=false),
     *                 @OA\Property(property="open_time", type="string", nullable=true, example="10:00"),
     *                 @OA\Property(property="close_time", type="string", nullable=true, example="16:00"),
     *                 @OA\Property(property="reason", type="string", nullable=true, example="Vespera de ano novo")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(response=404, description="Excecao nao encontrada"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function updateException(UpdateAgendaExceptionRequest $request, int $id)
    {
        $exception = AgendaException::find($id);

        if (! $exception) {
            return $this->errorResponse('Excecao de calendario nao encontrada.', 404);
        }

        $exception = $this->agendaService->updateException($exception, $request->validated());

        return $this->successResponse(
            CalendarExceptionPresenter::make($exception),
            'Excecao de calendario atualizada com sucesso.'
        );
    }
}
