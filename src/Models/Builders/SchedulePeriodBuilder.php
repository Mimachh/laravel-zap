<?php

namespace Zap\Models\Builders;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use PDO;

class SchedulePeriodBuilder extends Builder
{
    /**
     * Scope a query to only include available periods.
     */
    public function available(): SchedulePeriodBuilder
    {
        return $this->where('is_available', true);
    }

    /**
     * Scope a query to only include periods for a specific date.
     */
    public function forDate(string $date): SchedulePeriodBuilder
    {
        return $this->where('date', Carbon::parse($date));
    }

    /**
     * Scope a query to only include periods within a time range.
     */
    public function forTimeRange(string $startTime, string $endTime): SchedulePeriodBuilder
    {
        return $this->where('start_time', '>=', $startTime)
            ->where('end_time', '<=', $endTime);
    }

    /**
     * Scope a query to find overlapping periods.
     */
    public function overlapping(string $date, string $startTime, string $endTime, ?CarbonInterface $endDate = null): SchedulePeriodBuilder
    {
        // Normalize input times to HH:MM format
        $startTime = str_pad($startTime, 5, '0', STR_PAD_LEFT);
        $endTime = str_pad($endTime, 5, '0', STR_PAD_LEFT);

        // Apply date filter
        $this->when(is_null($endDate), fn ($q) => $q->whereDate('date', $date));

        // Apply time overlap logic based on database driver

        /** @var Connection $connection */
        $connection = $this->getConnection();
        $driver = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            return $this->applySqliteTimeOverlap($this, $startTime, $endTime);
        }

        if ($driver === 'pgsql') {
            return $this->applyPostgresTimeOverlap($this, $startTime, $endTime);
        }

        return $this->applyStandardTimeOverlap($this, $startTime, $endTime);
    }

    /**
     * Apply SQLite-specific time overlap conditions.
     */
    private function applySqliteTimeOverlap(SchedulePeriodBuilder $query, string $startTime, string $endTime): SchedulePeriodBuilder
    {
        return $query
            ->whereRaw('CASE WHEN LENGTH(start_time) = 4 THEN "0" || start_time ELSE start_time END < ?', [$endTime])
            ->whereRaw('CASE WHEN LENGTH(end_time) = 4 THEN "0" || end_time ELSE end_time END > ?', [$startTime]);
    }

    /**
     * Apply standard SQL time overlap conditions (MySQL).
     */
    private function applyStandardTimeOverlap(SchedulePeriodBuilder $query, string $startTime, string $endTime): SchedulePeriodBuilder
    {
        return $query
            ->whereRaw("LPAD(start_time, 5, '0') < ?", [$endTime])
            ->whereRaw("LPAD(end_time, 5, '0') > ?", [$startTime]);
    }

    /**
     * Apply PostgreSQL-specific time overlap conditions.
     */
    private function applyPostgresTimeOverlap(SchedulePeriodBuilder $query, string $startTime, string $endTime): SchedulePeriodBuilder
    {
        return $query
            ->whereRaw('LPAD(start_time::text, 5, \'0\') < ?', [$endTime])
            ->whereRaw('LPAD(end_time::text, 5, \'0\') > ?', [$startTime]);
    }
}
