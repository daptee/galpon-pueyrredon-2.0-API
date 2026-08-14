<?php

namespace App\Services;

use App\Models\BlockedDate;
use App\Models\Budget;
use App\Models\LogisticsCapacityConfig;
use Carbon\Carbon;

class LogisticsCapacityService
{
    public function getConfig(): LogisticsCapacityConfig
    {
        $config = LogisticsCapacityConfig::first();

        if (!$config) {
            $config = LogisticsCapacityConfig::create([
                'max_daily_events' => 0,
                'max_daily_volume' => 0,
                'max_successive_events' => 0,
                'max_successive_volume' => 0,
                'high_demand_threshold' => 60,
                'restricted_threshold' => 80,
            ]);
        }

        return $config;
    }

    public function findBlockedDate(string $date): ?BlockedDate
    {
        return BlockedDate::whereDate('date', $date)->first();
    }

    /**
     * Indica si la familia de versiones (padres + hijos) de un presupuesto ya tiene
     * una versión Aprobada (id_budget_status = 3). En ese caso el chequeo de
     * capacidad logística se omite: la fecha ya fue reservada previamente.
     */
    public function familyHasApprovedVersion(?int $anyMemberId): bool
    {
        if (!$anyMemberId) {
            return false;
        }

        $start = Budget::find($anyMemberId);
        if (!$start) {
            return false;
        }

        $root = $start;
        while ($root->id_budget) {
            $parent = Budget::find($root->id_budget);
            if (!$parent) {
                break;
            }
            $root = $parent;
        }

        if ((int) $root->id_budget_status === 3) {
            return true;
        }

        $stack = collect([$root]);
        while ($stack->isNotEmpty()) {
            $node = $stack->pop();
            $children = Budget::where('id_budget', $node->id)->get();
            foreach ($children as $child) {
                if ((int) $child->id_budget_status === 3) {
                    return true;
                }
                $stack->push($child);
            }
        }

        return false;
    }

    /**
     * Evalúa el semáforo de capacidad logística para una fecha.
     *
     * @param string $date Fecha del evento (Y-m-d)
     * @param bool $isClient true si quien está cargando el presupuesto es un cliente BtoB
     * @param float $currentVolume Volumen del presupuesto actual (el admin lo suma, el cliente no)
     * @param bool $isCalendarView true cuando se evalúa el estado de un día para el calendario
     *   de disponibilidad (sin sumar el presupuesto "actual", solo lectura de lo existente)
     */
    public function evaluate(string $date, bool $isClient, float $currentVolume = 0, bool $isCalendarView = false): array
    {
        $config = $this->getConfig();
        $blockedDate = $this->findBlockedDate($date);

        $carbonDate = Carbon::parse($date);
        $currentDate = $carbonDate->toDateString();
        $prevDate = $carbonDate->copy()->subDay()->toDateString();
        $nextDate = $carbonDate->copy()->addDay()->toDateString();

        $existingEventsDay = $this->countApprovedEvents($currentDate);
        $existingEventsPrev = $this->countApprovedEvents($prevDate);
        $existingEventsNext = $this->countApprovedEvents($nextDate);

        $existingVolumeDay = $this->sumApprovedVolume($currentDate);
        $existingVolumePrev = $this->sumApprovedVolume($prevDate);
        $existingVolumeNext = $this->sumApprovedVolume($nextDate);

        $eventsDelta = $isCalendarView ? 0 : 1;
        $volumeDelta = ($isCalendarView || $isClient) ? 0 : $currentVolume;

        $metrics = [
            'daily_events' => [
                'variable' => 'daily_events',
                'period' => 'day',
                'current' => $existingEventsDay + $eventsDelta,
                'max' => (int) $config->max_daily_events,
            ],
            'daily_volume' => [
                'variable' => 'daily_volume',
                'period' => 'day',
                'current' => round($existingVolumeDay + $volumeDelta, 2),
                'max' => (float) $config->max_daily_volume,
            ],
            'successive_events_prev' => [
                'variable' => 'successive_events',
                'period' => 'previous_pair',
                'current' => $existingEventsPrev + $existingEventsDay + $eventsDelta,
                'max' => (int) $config->max_successive_events,
            ],
            'successive_events_next' => [
                'variable' => 'successive_events',
                'period' => 'next_pair',
                'current' => $existingEventsDay + $existingEventsNext + $eventsDelta,
                'max' => (int) $config->max_successive_events,
            ],
            'successive_volume_prev' => [
                'variable' => 'successive_volume',
                'period' => 'previous_pair',
                'current' => round($existingVolumePrev + $existingVolumeDay + $volumeDelta, 2),
                'max' => (float) $config->max_successive_volume,
            ],
            'successive_volume_next' => [
                'variable' => 'successive_volume',
                'period' => 'next_pair',
                'current' => round($existingVolumeDay + $existingVolumeNext + $volumeDelta, 2),
                'max' => (float) $config->max_successive_volume,
            ],
        ];

        $violated = [];
        $maxPercentage = 0;
        $highThreshold = (float) $config->high_demand_threshold;
        $restrictedThreshold = (float) $config->restricted_threshold;

        foreach ($metrics as &$metric) {
            $percentage = $metric['max'] > 0
                ? round(($metric['current'] / $metric['max']) * 100, 2)
                : null;

            $metric['percentage'] = $percentage;

            if ($percentage !== null) {
                $maxPercentage = max($maxPercentage, $percentage);

                if ($percentage >= $highThreshold) {
                    $violated[] = [
                        'variable' => $metric['variable'],
                        'period' => $metric['period'],
                        'current' => $metric['current'],
                        'max' => $metric['max'],
                        'percentage' => $percentage,
                    ];
                }
            }
        }
        unset($metric);

        if ($blockedDate) {
            $status = 'fecha_cerrada';
        } elseif ($maxPercentage >= 100) {
            $status = 'excedida';
        } elseif ($maxPercentage >= $restrictedThreshold) {
            $status = 'restringida';
        } elseif ($maxPercentage >= $highThreshold) {
            $status = 'alta_demanda';
        } elseif ($isCalendarView && $existingEventsDay === 0) {
            $status = 'sin_eventos';
        } else {
            $status = 'amplia';
        }

        return [
            'date' => $currentDate,
            'status' => $status,
            'blocked_date' => $blockedDate ? [
                'id' => $blockedDate->id,
                'reason' => $blockedDate->reason,
            ] : null,
            'metrics' => $metrics,
            'violated' => $violated,
        ];
    }

    private function countApprovedEvents(string $date): int
    {
        return Budget::where('id_budget_status', 3)
            ->whereDate('date_event', $date)
            ->count();
    }

    private function sumApprovedVolume(string $date): float
    {
        return (float) Budget::where('id_budget_status', 3)
            ->whereDate('date_event', $date)
            ->sum('volume');
    }
}
