<?php

namespace Zap\Models\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Zap\Enums\Frequency;
use Zap\Enums\ScheduleTypes;
use Zap\Helper\DateHelper;

class ScheduleBuilder extends Builder
{
    public function active(bool $active = true): ScheduleBuilder
    {
        return $this->where('is_active', $active);
    }

    public function recurring(bool $recurring = true): ScheduleBuilder
    {
        return $this->where('is_recurring', $recurring);
    }

    /**
     * Scope a query to only include schedules of a specific type.
     */
    public function ofType(ScheduleTypes|string $type): ScheduleBuilder
    {
        return $this->where('schedule_type', $type);
    }

    /**
     * Scope a query to only include availability schedules.
     */
    public function availability(): ScheduleBuilder
    {
        return $this->where('schedule_type', ScheduleTypes::AVAILABILITY->value);
    }

    /**
     * Scope a query to only include appointment schedules.
     */
    public function appointments(): ScheduleBuilder
    {
        return $this->where('schedule_type', ScheduleTypes::APPOINTMENT->value);
    }

    /**
     * Scope a query to only include blocked schedules.
     */
    public function blocked(): ScheduleBuilder
    {
        return $this->where('schedule_type', ScheduleTypes::BLOCKED->value);
    }

    /**
     * Scope a query to only include schedules for a specific date.
     */
    public function forDate(string $date): ScheduleBuilder
    {
        $checkDate = Carbon::parse($date);
        $weekday = strtolower($checkDate->format('l')); // monday, tuesday, ...
        $dayOfMonth = $checkDate->day;
        $isDateInEvenIsoWeek = DateHelper::isDateInEvenIsoWeek($date);

        return $this
            // date range
            ->where('start_date', '<=', $checkDate)
            ->where(function ($q) use ($checkDate) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $checkDate);
            })

            // recurrence logic
            ->where(function ($q) use ($weekday, $dayOfMonth, $isDateInEvenIsoWeek) {

                //
                // 1️⃣ NOT RECURRING — always match
                //
                $q->where('is_recurring', false)

                    //
                    // 2️⃣ DAILY — match all days
                    //
                    ->orWhere(function ($daily) {
                        $daily->where('is_recurring', true)
                            ->where('frequency', Frequency::DAILY->value);
                    })

                    //
                    // 3️⃣ WEEKLY | BI-WEEKLY — match weekday inside config
                    //
                    ->orWhere(function ($weekly) use ($weekday) {
                        $weekly->where('is_recurring', true)
                            ->whereIn(
                                'frequency',
                                array_map(
                                    fn (Frequency $frequency) => $frequency->value,
                                    Frequency::filteredByWeekday()
                                )
                            )
                            ->whereJsonContains('frequency_config->days', $weekday);
                    })
                    //
                    // 4 WEEKLY_EVEN | WEEKLY_ODD — match weekday inside config
                    //
                    ->orWhere(function ($query) use ($weekday, $isDateInEvenIsoWeek) {
                        $query->where('is_recurring', true)
                            ->where('frequency', $isDateInEvenIsoWeek ? Frequency::WEEKLY_EVEN->value : Frequency::WEEKLY_ODD->value)
                            ->whereJsonContains('frequency_config->days', $weekday);
                    })

                    //
                    // 5️⃣ MONTHLY — match day_of_month from config
                    //
                    ->orWhere(function ($monthly) use ($dayOfMonth) {
                        $monthly->where('is_recurring', true)
                            ->whereIn(
                                'frequency',
                                array_map(
                                    fn (Frequency $frequency) => $frequency->value,
                                    Frequency::filteredByDaysOfMonth()
                                )
                            )
                            ->where(function ($m) use ($dayOfMonth) {
                                $m->whereJsonContains('frequency_config->days_of_month', $dayOfMonth)
                                    ->orWhere('frequency_config->days_of_month', $dayOfMonth);
                            });
                    });
            });
    }

    /**
     * Scope a query to only include schedules within a date range.
     */
    public function forDateRange(string $startDate, string $endDate): ScheduleBuilder
    {
        return $this->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where(
                            fn ($q3) => $q3->whereNull('end_date')->orWhere('end_date', '>=', $endDate),
                        );
                });
        });
    }
}
