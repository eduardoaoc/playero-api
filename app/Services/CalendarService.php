<?php

namespace App\Services;

use App\Models\AgendaBlocking;
use App\Models\AgendaConfig;
use App\Models\AgendaException;
use App\Models\Event;
use App\Models\Reserva;
use Illuminate\Support\Carbon;
use RuntimeException;

class CalendarService
{
    private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    public function getOverview(int $year, int $month): array
    {
        $config = AgendaConfig::query()->first();

        if (! $config) {
            throw new RuntimeException('Configuracao da agenda nao encontrada.');
        }

        $timezone = $this->resolveTimezone($config);
        $cursor = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->startOfMonth();
        $end = $cursor->copy()->endOfMonth();
        $startDate = $cursor->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        $exceptions = AgendaException::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn (AgendaException $exception) => $exception->date?->format('Y-m-d'));

        $reservationsByDate = Reserva::query()
            ->with(['quadra:id,nome'])
            ->whereBetween('data', [$startDate, $endDate])
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Reserva $reserva) => $this->normalizeDateKey($reserva->data));

        $eventsByDate = Event::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', Event::STATUS_ACTIVE)
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Event $event) => $this->normalizeDateKey($event->date));

        $blockingsByDate = AgendaBlocking::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderByRaw('start_time is null')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (AgendaBlocking $blocking) => $this->normalizeDateKey($blocking->date));

        $days = [];

        while ($cursor->lte($end)) {
            $dateString = $cursor->format('Y-m-d');
            $exception = $exceptions->get($dateString);
            $isClosedAllDay = false;

            if ($exception) {
                $isClosedAllDay = (bool) $exception->is_closed
                    || $exception->opening_time === null
                    || $exception->closing_time === null;
            } elseif (! $this->isActiveDay($cursor, $config->active_days ?? [])) {
                $isClosedAllDay = true;
            }

            $reservations = $reservationsByDate->get($dateString, collect());
            $events = $eventsByDate->get($dateString, collect());
            $blockings = $blockingsByDate->get($dateString, collect());

            $blockingsList = $blockings
                ->map(fn (AgendaBlocking $blocking) => [
                    'id' => $blocking->id,
                    'reason' => $blocking->reason,
                ])
                ->values()
                ->all();

            if ($isClosedAllDay && $exception) {
                $blockingsList[] = [
                    'id' => $exception->id,
                    'reason' => $exception->reason,
                ];
            }

            $days[] = [
                'date' => $dateString,
                'reservations' => $reservations->map(fn (Reserva $reserva) => [
                    'id' => $reserva->id,
                    'quadra' => $reserva->quadra?->nome,
                    'start_time' => $reserva->hora_inicio,
                    'end_time' => $reserva->hora_fim,
                    'status' => $this->mapReservationStatus($reserva->status),
                    'payment_method' => $this->normalizePaymentMethod($reserva->forma_pagamento),
                    'value' => (float) ($reserva->valor ?? 0),
                ])->values()->all(),
                'events' => $events->map(fn (Event $event) => [
                    'id' => $event->id,
                    'title' => $event->name,
                    'type' => $event->visibility,
                    'start' => $event->start_time,
                    'end' => $event->end_time,
                ])->values()->all(),
                'blockings' => $blockingsList,
                'closed_all_day' => $isClosedAllDay,
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

    private function normalizeDateKey($value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function isActiveDay(Carbon $date, array $activeDays): bool
    {
        if (empty($activeDays)) {
            return true;
        }

        $weekday = (int) $date->isoWeekday();

        return in_array($weekday, $activeDays, true);
    }

    private function mapReservationStatus(?string $status): ?string
    {
        return match ($status) {
            Reserva::STATUS_PENDENTE_PAGAMENTO => 'PRE_RESERVA',
            Reserva::STATUS_CONFIRMADA => 'CONFIRMADA',
            Reserva::STATUS_CANCELADA => 'CANCELADA',
            Reserva::STATUS_EXPIRADA => 'EXPIRADA',
            default => $status ? strtoupper($status) : null,
        };
    }

    private function normalizePaymentMethod(?string $method): ?string
    {
        if ($method === null) {
            return null;
        }

        $trimmed = trim($method);

        if ($trimmed === '' || $trimmed === '---') {
            return null;
        }

        return $trimmed;
    }
}
