<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Models\Event;
use App\Models\Role;
use App\Support\ApiResponse;
use App\Support\EventPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Annotations as OA;

class EventController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/events",
     *     tags={"Eventos"},
     *     summary="Listar eventos",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Eventos listados",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Eventos listados com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=12),
     *                     @OA\Property(property="name", type="string", example="Aniversario Joao"),
     *                     @OA\Property(property="type", type="string", example="aniversario"),
     *                     @OA\Property(property="date", type="string", format="date", example="2025-03-22"),
     *                     @OA\Property(property="start_time", type="string", example="18:00"),
     *                     @OA\Property(property="end_time", type="string", example="23:00"),
     *                     @OA\Property(property="location", type="string", example="Area VIP"),
     *                     @OA\Property(property="max_people", type="integer", example=120),
     *                     @OA\Property(property="visibility", type="string", example="publico"),
     *                     @OA\Property(property="is_paid", type="boolean", example=true),
     *                     @OA\Property(property="status", type="string", example="ativo"),
     *                     @OA\Property(property="description", type="string", example="Evento privado com musica ao vivo e buffet"),
     *                     @OA\Property(property="created_by", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao")
     * )
     */
    public function index()
    {
        $events = Event::query()
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        return $this->successResponse(
            $events->map(fn (Event $event) => EventPresenter::make($event))->all(),
            'Eventos listados com sucesso.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/events",
     *     tags={"Eventos"},
     *     summary="Criar evento",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","type","date","start_time","end_time","location","visibility","is_paid","status"},
     *             @OA\Property(property="name", type="string", example="Aniversario Joao"),
     *             @OA\Property(property="type", type="string", example="aniversario"),
     *             @OA\Property(property="date", type="string", format="date", example="2025-03-22"),
     *             @OA\Property(property="start_time", type="string", example="18:00"),
     *             @OA\Property(property="end_time", type="string", example="23:00"),
     *             @OA\Property(property="location", type="string", example="Area VIP"),
     *             @OA\Property(property="max_people", type="integer", nullable=true, example=120),
     *             @OA\Property(property="visibility", type="string", example="publico"),
     *             @OA\Property(property="is_paid", type="boolean", example=true),
     *             @OA\Property(property="status", type="string", example="ativo"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Evento privado com musica ao vivo e buffet")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Evento criado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Evento criado com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="name", type="string", example="Aniversario Joao"),
     *                 @OA\Property(property="type", type="string", example="aniversario"),
     *                 @OA\Property(property="date", type="string", format="date", example="2025-03-22"),
     *                 @OA\Property(property="start_time", type="string", example="18:00"),
     *                 @OA\Property(property="end_time", type="string", example="23:00"),
     *                 @OA\Property(property="location", type="string", example="Area VIP"),
     *                 @OA\Property(property="max_people", type="integer", example=120),
     *                 @OA\Property(property="visibility", type="string", example="publico"),
     *                 @OA\Property(property="is_paid", type="boolean", example=true),
     *                 @OA\Property(property="status", type="string", example="ativo"),
     *                 @OA\Property(property="description", type="string", example="Evento privado com musica ao vivo e buffet"),
     *                 @OA\Property(property="created_by", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=403, description="Sem permissao"),
     *     @OA\Response(
     *         response=422,
     *         description="Dados invalidos",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The date field must be a date after or equal to today.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreEventRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $event = Event::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'location' => $data['location'],
            'max_people' => $data['max_people'] ?? null,
            'visibility' => $data['visibility'],
            'is_paid' => $data['is_paid'],
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'created_by' => $user->id,
        ]);

        return $this->successResponse(
            EventPresenter::make($event),
            'Evento criado com sucesso.',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/events/calendar",
     *     tags={"Eventos"},
     *     summary="Listar eventos do calendario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", example="2025-03")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eventos agrupados por data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Eventos carregados com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="2025-03-22",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="date", type="string", format="date", example="2025-03-22"),
     *                         @OA\Property(property="start_time", type="string", example="18:00"),
     *                         @OA\Property(property="end_time", type="string", example="23:00"),
     *                         @OA\Property(property="name", type="string", example="Aniversario Joao"),
     *                         @OA\Property(property="location", type="string", example="Area VIP"),
     *                         @OA\Property(property="type", type="string", example="aniversario"),
     *                         @OA\Property(property="status", type="string", example="ativo")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Nao autenticado"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function calendar(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $start = $month->copy()->startOfMonth()->format('Y-m-d');
        $end = $month->copy()->endOfMonth()->format('Y-m-d');
        $user = $request->user();
        $isAdmin = $user && $user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN]);

        $query = Event::query()
            ->whereBetween('date', [$start, $end])
            ->where('status', Event::STATUS_ACTIVE);

        if (! $isAdmin) {
            $query->whereIn('visibility', [Event::VISIBILITY_PUBLIC, 'public']);
        }

        $events = $query
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $grouped = $events
            ->groupBy(fn (Event $event) => $event->date ? $event->date->format('Y-m-d') : $event->date)
            ->map(fn ($items) => $items->map(fn (Event $event) => [
                'date' => $event->date ? $event->date->format('Y-m-d') : null,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'name' => $event->name,
                'location' => $event->location,
                'type' => $event->type,
                'status' => $event->status,
            ])->values()->all())
            ->all();

        return $this->successResponse($grouped, 'Eventos carregados com sucesso.');
    }
}
