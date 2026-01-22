<?php

namespace Zap\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;
use Zap\Models\Builders\SchedulePeriodBuilder;

/**
 * @property int|string $id
 * @property int|string $schedule_id
 * @property Carbon $date
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property bool $is_available
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Schedule $schedule
 * @property-read int $duration_minutes
 * @property-read Carbon $start_date_time
 * @property-read Carbon $end_date_time
 *
 * @method static \Illuminate\Database\Eloquent\Builder available()
 * @method static \Illuminate\Database\Eloquent\Builder forDate(string $date)
 * @method static \Illuminate\Database\Eloquent\Builder forTimeRange(string $startTime, string $endTime)
 * @method static \Illuminate\Database\Eloquent\Builder overlapping(string $date, string $startTime, string $endTime, ?CarbonInterface $endDate = null)
 */
class SchedulePeriod extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'schedule_periods';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'schedule_id',
        'date',
        'start_time',
        'end_time',
        'is_available',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'is_available' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Retrieve the FQCN of the class to use for Schedule models.
     *
     * @return class-string<Schedule>
     */
    protected function getScheduleClass(): string
    {
        return config('zap.models.schedule', Schedule::class);
    }

    /**
     * Get the schedule that owns the period.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo($this->getScheduleClass(), 'schedule_id');
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder($query): SchedulePeriodBuilder
    {
        return new SchedulePeriodBuilder($query);
    }

    /**
     * Get the duration in minutes.
     */
    public function getDurationMinutesAttribute(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        $baseDate = '2024-01-01'; // Use a consistent base date for time parsing
        $start = Carbon::parse($baseDate.' '.$this->start_time);
        $end = Carbon::parse($baseDate.' '.$this->end_time);

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Get the full start datetime.
     */
    public function getStartDateTimeAttribute(): CarbonInterface
    {
        return Date::parse($this->date->format('Y-m-d').' '.$this->start_time);
    }

    /**
     * Get the full end datetime.
     */
    public function getEndDateTimeAttribute(): CarbonInterface
    {
        return Date::parse($this->date->format('Y-m-d').' '.$this->end_time);
    }

    /**
     * Check if this period overlaps with another period.
     */
    public function overlapsWith(SchedulePeriod $other): bool
    {
        // Must be on the same date
        if (! $this->date->eq($other->date)) {
            return false;
        }

        return $this->start_time < $other->end_time && $this->end_time > $other->start_time;
    }

    /**
     * Check if this period is currently active (happening now).
     */
    public function isActiveNow(): bool
    {
        $now = Carbon::now();
        $startDateTime = $this->start_date_time;
        $endDateTime = $this->end_date_time;

        return $now->between($startDateTime, $endDateTime);
    }

    /**
     * Convert the period to a human-readable string.
     */
    public function __toString(): string
    {
        return sprintf(
            '%s from %s to %s',
            $this->date->format('Y-m-d'),
            $this->start_time,
            $this->end_time
        );
    }
}
