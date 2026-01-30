<?php

/**
 * Tests for single-day (non-recurring) schedules with NULL end_date
 *
 * Issue: When creating a single-day appointment using ->on('date') without ->to(),
 * the schedule incorrectly appears when querying any date after the appointment date.
 *
 * A non-recurring schedule with NULL end_date should only match its exact start_date,
 * not all future dates.
 */

use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Single-day schedules with NULL end_date', function () {

    it('should only return non-recurring schedule on exact start_date when end_date is NULL', function () {
        $user = createUser();

        // Create single-day appointment as shown in docs: ->on('date') without ->to()
        Zap::for($user)
            ->named('Doctor Appointment')
            ->appointment()
            ->on('2025-03-15')
            ->addPeriod('09:00', '10:00')
            ->save();

        // Should return the schedule on the exact date
        $schedulesOnDate = Schedule::active()
            ->forDate('2025-03-15')
            ->get();

        expect($schedulesOnDate)->toHaveCount(1, 'Should return schedule on exact start_date');
        expect($schedulesOnDate->first()->name)->toBe('Doctor Appointment');

        // Should NOT return the schedule on the next day
        $schedulesNextDay = Schedule::active()
            ->forDate('2025-03-16')
            ->get();

        expect($schedulesNextDay)->toHaveCount(0, 'Should not return schedule on day after start_date');

        // Should NOT return the schedule on any future date
        $schedulesFuture = Schedule::active()
            ->forDate('2025-04-01')
            ->get();

        expect($schedulesFuture)->toHaveCount(0, 'Should not return schedule on future dates');
    });

    it('should return non-recurring schedule on any date within range when end_date is set', function () {
        $user = createUser();

        // Create multi-day non-recurring schedule with explicit end_date
        Zap::for($user)
            ->named('Conference')
            ->appointment()
            ->from('2025-03-15')
            ->to('2025-03-17')
            ->addPeriod('09:00', '17:00')
            ->save();

        // Should return on start date
        $schedulesStart = Schedule::active()
            ->forDate('2025-03-15')
            ->get();

        expect($schedulesStart)->toHaveCount(1, 'Should return schedule on start_date');

        // Should return on middle date
        $schedulesMiddle = Schedule::active()
            ->forDate('2025-03-16')
            ->get();

        expect($schedulesMiddle)->toHaveCount(1, 'Should return schedule on date within range');

        // Should return on end date
        $schedulesEnd = Schedule::active()
            ->forDate('2025-03-17')
            ->get();

        expect($schedulesEnd)->toHaveCount(1, 'Should return schedule on end_date');

        // Should NOT return after end date
        $schedulesAfter = Schedule::active()
            ->forDate('2025-03-18')
            ->get();

        expect($schedulesAfter)->toHaveCount(0, 'Should not return schedule after end_date');
    });

    it('should not return single-day appointment on previous dates', function () {
        $user = createUser();

        Zap::for($user)
            ->named('Future Appointment')
            ->appointment()
            ->on('2025-03-15')
            ->addPeriod('14:00', '15:00')
            ->save();

        // Should NOT return on dates before start_date
        $schedulesBefore = Schedule::active()
            ->forDate('2025-03-14')
            ->get();

        expect($schedulesBefore)->toHaveCount(0, 'Should not return schedule before start_date');
    });

    it('should handle multiple single-day appointments correctly', function () {
        $user = createUser();

        // Create appointments on different days
        Zap::for($user)
            ->named('Monday Appointment')
            ->appointment()
            ->on('2025-03-10')
            ->addPeriod('09:00', '10:00')
            ->save();

        Zap::for($user)
            ->named('Wednesday Appointment')
            ->appointment()
            ->on('2025-03-12')
            ->addPeriod('14:00', '15:00')
            ->save();

        // Query Monday - should only return Monday appointment
        $mondaySchedules = Schedule::active()
            ->forDate('2025-03-10')
            ->get();

        expect($mondaySchedules)->toHaveCount(1);
        expect($mondaySchedules->first()->name)->toBe('Monday Appointment');

        // Query Tuesday - should return nothing
        $tuesdaySchedules = Schedule::active()
            ->forDate('2025-03-11')
            ->get();

        expect($tuesdaySchedules)->toHaveCount(0, 'Should not return any schedule on Tuesday');

        // Query Wednesday - should only return Wednesday appointment
        $wednesdaySchedules = Schedule::active()
            ->forDate('2025-03-12')
            ->get();

        expect($wednesdaySchedules)->toHaveCount(1);
        expect($wednesdaySchedules->first()->name)->toBe('Wednesday Appointment');
    });
});
