<?php

namespace App\Services;

use App\Models\Project;

/**
 * DynamicRoutineEngine
 *
 * Recalculates a project's daily routine when the day starts late
 * or a delay ripples through the schedule.
 *
 * Strategy:
 *  1. Fetch the active routine and its slots (sorted by start_time).
 *  2. Apply a cascading delay: every slot that starts at or after the
 *     delay point is shifted forward by `$delayMinutes`.
 *  3. Break slots that overflow the end-of-day boundary and mark them
 *     as "trimmed".
 *  4. Return the adjusted schedule array — no DB writes here, the
 *     caller decides whether to persist or just display the preview.
 */
class DynamicRoutineEngine
{
    /**
     * Recalculate the routine starting from the current time + delay.
     *
     * @param  Project  $project
     * @param  int      $delayMinutes  How many minutes late the day started.
     * @param  string   $pivotTime     HH:MM — slots at or after this time get shifted.
     *                                 Defaults to current time.
     * @return array    Array of adjusted slot arrays with keys:
     *                  id, label, category, color,
     *                  original_start, original_end,
     *                  adjusted_start, adjusted_end,
     *                  duration_minutes, trimmed (bool), overflow (bool)
     */
    public function recalculate(Project $project, int $delayMinutes, string $pivotTime = null): array
    {
        $pivotTime ??= now()->format('H:i');
        $pivotMinutes = $this->toMinutes($pivotTime);

        $routine = $project->routine()->with('slots')->first();
        if (! $routine) {
            return [];
        }

        $endOfDay = 24 * 60; // 00:00 next day = 1440 min
        $adjusted = [];

        foreach ($routine->slots as $slot) {
            $start = $this->toMinutes($slot->start_time);
            $end   = $this->toMinutes($slot->end_time);
            $dur   = $end - $start;

            $newStart = $start;
            $newEnd   = $end;
            $trimmed  = false;
            $overflow = false;

            if ($start >= $pivotMinutes) {
                $newStart = $start + $delayMinutes;
                $newEnd   = $newStart + $dur;

                if ($newEnd > $endOfDay) {
                    $overflow = true;
                    if ($newStart >= $endOfDay) {
                        // Slot completely overflows — skip it
                        continue;
                    }
                    $newEnd  = $endOfDay;
                    $trimmed = true;
                }
            }

            $adjusted[] = [
                'id'               => $slot->id,
                'label'            => $slot->label,
                'category'         => $slot->category,
                'color'            => $slot->color,
                'original_start'   => $slot->start_time,
                'original_end'     => $slot->end_time,
                'adjusted_start'   => $this->fromMinutes($newStart),
                'adjusted_end'     => $this->fromMinutes($newEnd),
                'duration_minutes' => $newEnd - $newStart,
                'trimmed'          => $trimmed,
                'overflow'         => $overflow,
            ];
        }

        return $adjusted;
    }

    /**
     * Convert HH:MM string to total minutes from midnight.
     */
    private function toMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return $h * 60 + $m;
    }

    /**
     * Convert total minutes from midnight back to HH:MM string.
     */
    private function fromMinutes(int $minutes): string
    {
        $minutes = max(0, min(1439, $minutes));
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
