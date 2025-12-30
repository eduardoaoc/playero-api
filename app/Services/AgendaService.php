<?php

namespace App\Services;

use App\Models\AgendaBlocking;
use App\Models\AgendaConfig;
use App\Models\AgendaException;
use App\Models\Event;
use App\Models\Reserva;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class AgendaService
{
    private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    public function getConfig(): ?AgendaConfig
    {
        return AgendaConfig::query()->first();
    }

    public function getTimezone(AgendaConfig $config): string
    {
        return $this->resolveTimezone($config);
    }

    public function upsertConfig(array $payload): AgendaConfig
    {
        $config = AgendaConfig::query()->first();
        $created = false;
        $defaultActiveDays = [1, 2, 3, 4, 5, 6, 7];

        if ($config) {
            $slotDuration = array_key_exists('slot_duration', $payload)
                ? $payload['slot_duration']
                : $config->slot_duration;
            $activeDays = array_key_exists('active_days', $payload)
                ? $payload['active_days']
                : $config->active_days;

            $config->fill([
                'opening_time' => $payload['opening_time'],
                'closing_time' => $payload['closing_time'],
                'slot_duration' => $slotDuration,
                'active_days' => $activeDays ?? $defaultActiveDays,
                'timezone' => $payload['timezone'] ?? $config->timezone,
            ]);
            $config->save();
        } else {
            $slotDuration = array_key_exists('slot_duration', $payload)
                ? $payload['slot_duration']
                : 60;
            $activeDays = array_key_exists('active_days', $payload)
                ? $payload['active_days']
                : $defaultActiveDays;

            $config = AgendaConfig::create([
                'opening_time' => $payload['opening_time'],
                'closing_time' => $payload['closing_time'],
                'slot_duration' => $slotDuration,
                'active_days' => $activeDays,
                'timezone' => $payload['timezone'] ?? self::DEFAULT_TIMEZONE,
            ]);
            $created = true;
        }

        AgendaConfig::query()
            ->where('id', '!=', $config->id)
            ->delete();

        $config->setAttribute('was_created', $created);

        return $config;
    }

    public function listExceptions(): Collection
    {
        return AgendaException::query()
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    public function createException(array $payload): AgendaException
    {
        return AgendaException::create($this->normalizeExceptionPayload($payload));
    }

    public function updateException(AgendaException $exception, array $payload): AgendaException
    {
        $exception->fill($this->normalizeExceptionPayload($payload));
        $exception->save();

        return $exception;
    }

    public function deleteException(AgendaException $exception): void
    {
        $exception->delete();
    }

    public function listBlockings(): Collection
    {
        return AgendaBlocking::query()
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    public function createBlocking(array $payload): AgendaBlocking
    {
        $normalized = $this->normalizeBlockingPayload($payload);
        $this->ensureBlockingWithinHours($normalized);

        return AgendaBlocking::create($normalized);
    }

    public function deleteBlocking(AgendaBlocking $blocking): void
    {
        $blocking->delete();
    }

    public function getDayAvailability(Carbon $date, ?int $quadraId): array
    {
        $config = $this->getConfig();

        if (! $config) {
            throw new RuntimeException('Configuracao da agenda nao encontrada.');
        }

        $timezone = $this->resolveTimezone($config);
        $dateString = $date->format('Y-m-d');
        $day = Carbon::createFromFormat('Y-m-d', $dateString, $timezone);

        $workingHours = $this->resolveWorkingHours($day, $config);
        if ($workingHours['is_closed']) {
            return [
                'date' => $dateString,
                'is_closed' => true,
                'source' => $workingHours['source'],
                'reason' => $workingHours['reason'],
                'slots' => [],
            ];
        }

        $open = $this->buildDateTime($dateString, $workingHours['opening_time'], $timezone);
        $close = $this->buildDateTime($dateString, $workingHours['closing_time'], $timezone);
        $duration = (int) $config->slot_duration;
        $now = Carbon::now($timezone);

        if ($duration <= 0) {
            throw new RuntimeException('Duracao de reserva invalida.');
        }

        $blockings = $this->getBlockings($dateString, $quadraId);
        $events = $this->getEvents($dateString);
        $reservas = $this->getReservas($dateString, $quadraId);

        $slots = [];

        for ($slotStart = $open->copy(); $slotStart->lt($close); $slotStart->addMinutes($duration)) {
            $slotEnd = $slotStart->copy()->addMinutes($duration);

            if ($slotEnd->gt($close)) {
                break;
            }

            $status = $this->resolveSlotStatus(
                $slotStart,
                $slotEnd,
                $now,
                $blockings,
                $events,
                $reservas,
                $dateString,
                $timezone
            );

            $slots[] = [
                'start' => $slotStart->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'available' => $status['available'],
                'reason' => $status['reason'],
            ];
        }

        return [
            'date' => $dateString,
            'is_closed' => false,
            'source' => $workingHours['source'],
            'reason' => $workingHours['reason'],
            'slots' => $slots,
        ];
    }

    public function getSlotAvailability(Carbon $date, string $startTime, int $quadraId): array
    {
        $config = $this->getConfig();

        if (! $config) {
            throw new RuntimeException('Configuracao da agenda nao encontrada.');
        }

        $timezone = $this->resolveTimezone($config);
        $dateString = $date->format('Y-m-d');
        $day = Carbon::createFromFormat('Y-m-d', $dateString, $timezone);
        $duration = (int) $config->slot_duration;

        if ($duration <= 0) {
            throw new RuntimeException('Duracao de reserva invalida.');
        }

        $workingHours = $this->resolveWorkingHours($day, $config);
        if ($workingHours['is_closed']) {
            return [
                'available' => false,
                'reason' => 'closed',
            ];
        }

        $open = $this->buildDateTime($dateString, $workingHours['opening_time'], $timezone);
        $close = $this->buildDateTime($dateString, $workingHours['closing_time'], $timezone);
        $start = $this->buildDateTime($dateString, $startTime, $timezone);
        $end = $start->copy()->addMinutes($duration);

        if ($start->lt($open) || $end->gt($close)) {
            return [
                'available' => false,
                'reason' => 'outside_hours',
            ];
        }

        $status = $this->resolveSlotStatus(
            $start,
            $end,
            Carbon::now($timezone),
            $this->getBlockings($dateString, $quadraId),
            $this->getEvents($dateString),
            $this->getReservas($dateString, $quadraId),
            $dateString,
            $timezone
        );

        return [
            'available' => $status['available'],
            'reason' => $status['reason'],
            'end_time' => $end->format('H:i'),
        ];
    }

    public function hasReservationConflict(
        int $quadraId,
        string $date,
        string $startTime,
        string $endTime
    ): bool {
        return Reserva::query()
            ->where('quadra_id', $quadraId)
            ->where('data', $date)
            ->whereIn('status', Reserva::ACTIVE_STATUSES)
            ->where('hora_inicio', '<', $endTime)
            ->where('hora_fim', '>', $startTime)
            ->exists();
    }

    public function getMonthAvailability(Carbon $month): array
    {
        $config = $this->getConfig();

        if (! $config) {
            throw new RuntimeException('Configuracao da agenda nao encontrada.');
        }

        $timezone = $this->resolveTimezone($config);
        $cursor = Carbon::createFromFormat('Y-m', $month->format('Y-m'), $timezone)->startOfMonth();
        $end = $cursor->copy()->endOfMonth();

        $exceptions = AgendaException::query()
            ->whereBetween('date', [$cursor->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(fn (AgendaException $exception) => $exception->date?->format('Y-m-d'));

        $eventsByDate = Event::query()
            ->whereBetween('date', [$cursor->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->groupBy(fn (Event $event) => $event->date?->format('Y-m-d'));

        $blockingsByDate = AgendaBlocking::query()
            ->whereBetween('date', [$cursor->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereNull('quadra_id')
            ->get()
            ->groupBy(fn (AgendaBlocking $blocking) => $blocking->date?->format('Y-m-d'));

        $days = [];

        while ($cursor->lte($end)) {
            $dateString = $cursor->format('Y-m-d');
            $exception = $exceptions->get($dateString);
            $hasEvents = $eventsByDate->has($dateString);
            $hasGlobalBlocking = $blockingsByDate->has($dateString);

            if ($exception) {
                $isClosed = (bool) $exception->is_closed
                    || $exception->opening_time === null
                    || $exception->closing_time === null;
                if ($isClosed) {
                    $days[] = [
                        'date' => $dateString,
                        'status' => 'closed',
                        'source' => 'exception',
                    ];
                    $cursor->addDay();
                    continue;
                }

                $days[] = [
                    'date' => $dateString,
                    'status' => 'partial',
                    'source' => 'exception',
                ];
                $cursor->addDay();
                continue;
            }

            if (! $this->isActiveDay($cursor, $config->active_days)) {
                $days[] = [
                    'date' => $dateString,
                    'status' => 'closed',
                    'source' => 'config',
                ];
                $cursor->addDay();
                continue;
            }

            if ($hasEvents) {
                $days[] = [
                    'date' => $dateString,
                    'status' => 'partial',
                    'source' => 'event',
                ];
                $cursor->addDay();
                continue;
            }

            if ($hasGlobalBlocking) {
                $days[] = [
                    'date' => $dateString,
                    'status' => 'partial',
                    'source' => 'blocking',
                ];
                $cursor->addDay();
                continue;
            }

            $days[] = [
                'date' => $dateString,
                'status' => 'available',
            ];
            $cursor->addDay();
        }

        return $days;
    }

    private function resolveTimezone(AgendaConfig $config): string
    {
        $timezone = $config->timezone;

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        return self::DEFAULT_TIMEZONE;
    }

    private function resolveWorkingHours(Carbon $date, AgendaConfig $config): array
    {
        $exception = AgendaException::query()
            ->where('date', $date->format('Y-m-d'))
            ->first();

        if ($exception) {
            $isClosed = (bool) $exception->is_closed
                || $exception->opening_time === null
                || $exception->closing_time === null;

            return [
                'opening_time' => $exception->opening_time,
                'closing_time' => $exception->closing_time,
                'is_closed' => $isClosed,
                'source' => 'exception',
                'reason' => $exception->reason,
            ];
        }

        if (! $this->isActiveDay($date, $config->active_days)) {
            return [
                'opening_time' => null,
                'closing_time' => null,
                'is_closed' => true,
                'source' => 'config',
                'reason' => null,
            ];
        }

        return [
            'opening_time' => $config->opening_time,
            'closing_time' => $config->closing_time,
            'is_closed' => false,
            'source' => 'config',
            'reason' => null,
        ];
    }

    private function normalizeExceptionPayload(array $payload): array
    {
        $isClosed = (bool) ($payload['is_closed'] ?? false);

        $opening = $payload['opening_time'] ?? null;
        $closing = $payload['closing_time'] ?? null;

        if ($isClosed) {
            $opening = null;
            $closing = null;
        }

        return [
            'date' => $payload['date'],
            'opening_time' => $opening,
            'closing_time' => $closing,
            'is_closed' => $isClosed,
            'reason' => $payload['reason'] ?? null,
        ];
    }

    private function normalizeBlockingPayload(array $payload): array
    {
        return [
            'quadra_id' => $payload['quadra_id'] ?? null,
            'date' => $payload['date'],
            'start_time' => $payload['start_time'] ?? null,
            'end_time' => $payload['end_time'] ?? null,
            'reason' => $payload['reason'] ?? null,
        ];
    }

    private function isActiveDay(Carbon $date, array $activeDays): bool
    {
        if (empty($activeDays)) {
            return true;
        }

        $weekday = (int) $date->isoWeekday();

        return in_array($weekday, $activeDays, true);
    }

    private function buildDateTime(string $date, string $time, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, $timezone);
    }

    private function getBlockings(string $date, ?int $quadraId): Collection
    {
        return AgendaBlocking::query()
            ->where('date', $date)
            ->when(
                $quadraId,
                fn ($query) => $query->where(function ($subQuery) use ($quadraId) {
                    $subQuery->whereNull('quadra_id')->orWhere('quadra_id', $quadraId);
                }),
                fn ($query) => $query->whereNull('quadra_id')
            )
            ->get();
    }

    private function getEvents(string $date): Collection
    {
        return Event::query()
            ->where('date', $date)
            ->get();
    }

    private function getReservas(string $date, ?int $quadraId): Collection
    {
        if (! $quadraId) {
            return collect();
        }

        return Reserva::query()
            ->where('data', $date)
            ->where('quadra_id', $quadraId)
            ->whereIn('status', Reserva::ACTIVE_STATUSES)
            ->get(['hora_inicio', 'hora_fim']);
    }

    private function resolveSlotStatus(
        Carbon $start,
        Carbon $end,
        Carbon $now,
        Collection $blockings,
        Collection $events,
        Collection $reservas,
        string $date,
        string $timezone
    ): array {
        if ($start->lt($now)) {
            return [
                'available' => false,
                'reason' => 'past',
            ];
        }

        foreach ($blockings as $blocking) {
            if ($blocking->start_time === null || $blocking->end_time === null) {
                return [
                    'available' => false,
                    'reason' => 'blocking',
                ];
            }

            $blockStart = $this->buildDateTime($date, $blocking->start_time, $timezone);
            $blockEnd = $this->buildDateTime($date, $blocking->end_time, $timezone);

            if ($this->intervalOverlaps($start, $end, $blockStart, $blockEnd)) {
                return [
                    'available' => false,
                    'reason' => 'blocking',
                ];
            }
        }

        foreach ($events as $event) {
            $eventStart = $this->buildDateTime($date, $event->start_time, $timezone);
            $eventEnd = $this->buildDateTime($date, $event->end_time, $timezone);

            if ($this->intervalOverlaps($start, $end, $eventStart, $eventEnd)) {
                return [
                    'available' => false,
                    'reason' => 'event',
                ];
            }
        }

        foreach ($reservas as $reserva) {
            $reservaStart = $this->buildDateTime($date, $reserva->hora_inicio, $timezone);
            $reservaEnd = $this->buildDateTime($date, $reserva->hora_fim, $timezone);

            if ($this->intervalOverlaps($start, $end, $reservaStart, $reservaEnd)) {
                return [
                    'available' => false,
                    'reason' => 'reservation',
                ];
            }
        }

        return [
            'available' => true,
            'reason' => null,
        ];
    }

    private function intervalOverlaps(
        Carbon $start,
        Carbon $end,
        Carbon $otherStart,
        Carbon $otherEnd
    ): bool {
        return $start->lt($otherEnd) && $end->gt($otherStart);
    }

    private function ensureBlockingWithinHours(array $payload): void
    {
        if ($payload['start_time'] === null && $payload['end_time'] === null) {
            return;
        }

        if ($payload['start_time'] === null || $payload['end_time'] === null) {
            throw new RuntimeException('Horario do bloqueio invalido.');
        }

        $config = $this->getConfig();

        if (! $config) {
            throw new RuntimeException('Configuracao da agenda nao encontrada.');
        }

        $timezone = $this->resolveTimezone($config);
        $date = Carbon::createFromFormat('Y-m-d', $payload['date'], $timezone);
        $workingHours = $this->resolveWorkingHours($date, $config);

        if ($workingHours['is_closed']) {
            throw new RuntimeException('Dia fechado para bloqueios.');
        }

        $open = $this->buildDateTime($payload['date'], $workingHours['opening_time'], $timezone);
        $close = $this->buildDateTime($payload['date'], $workingHours['closing_time'], $timezone);
        $start = $this->buildDateTime($payload['date'], $payload['start_time'], $timezone);
        $end = $this->buildDateTime($payload['date'], $payload['end_time'], $timezone);

        if ($start->lt($open) || $end->gt($close)) {
            throw new RuntimeException('Horario do bloqueio fora do funcionamento.');
        }
    }
}
