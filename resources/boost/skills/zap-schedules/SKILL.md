# Laravel Zap - Schedule Management

Laravel Zap is a comprehensive calendar and scheduling system for Laravel. This skill covers schedule creation, types, and the fluent builder API.

## Installation

```bash
composer require laraveljutsu/zap
php artisan vendor:publish --provider="Zap\ZapServiceProvider"
php artisan migrate
```

## Model Setup

Add the `HasSchedules` trait to any Eloquent model you want to make schedulable:

```php
use Zap\Models\Concerns\HasSchedules;

class Doctor extends Model
{
    use HasSchedules;
}
```

## Schedule Types

Zap uses four schedule types:

| Type | Purpose | Overlap behavior |
|------|---------|------------------|
| **Availability** | Define when resources can be booked | Allows overlaps |
| **Appointment** | Actual bookings or scheduled events | Exclusive — no overlaps allowed |
| **Blocked** | Periods where booking is forbidden | Exclusive — no overlaps allowed |
| **Custom** | Neutral schedules with explicit rules | You define the rules |

## Creating Schedules

Use the `Zap` facade or `zap()` helper with the fluent builder:

```php
use Zap\Facades\Zap;

// Define availability (working hours)
Zap::for($doctor)
    ->named('Office Hours')
    ->availability()
    ->forYear(2025)
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Block time (lunch break)
Zap::for($doctor)
    ->named('Lunch Break')
    ->blocked()
    ->forYear(2025)
    ->addPeriod('12:00', '13:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Create appointment
Zap::for($doctor)
    ->named('Patient A - Consultation')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1, 'type' => 'consultation'])
    ->save();

// Custom schedule with explicit overlap rules
Zap::for($user)
    ->named('Custom Event')
    ->custom()
    ->from('2025-01-15')
    ->addPeriod('15:00', '16:00')
    ->noOverlap()
    ->save();
```

Recurrence is set via methods such as `daily()`, `weekly(['monday', 'friday'])`, `monthly(['days_of_month' => [1, 15]])`, and **ordinal weekday**: `firstWednesdayOfMonth()`, `secondFridayOfMonth()`, `lastMondayOfMonth()` (see zap-recurrence skill for all patterns).

## Date Range Methods

```php
$schedule->from('2025-01-15');                          // Single date
$schedule->on('2025-01-15');                            // Alias for from()
$schedule->from('2025-01-01')->to('2025-12-31');        // Date range
$schedule->between('2025-01-01', '2025-12-31');         // Alternative syntax
$schedule->forYear(2025);                               // Entire year shortcut
```

## Time Periods

```php
// Single period
$schedule->addPeriod('09:00', '17:00');

// Multiple periods (split shifts)
$schedule->addPeriod('09:00', '12:00');
$schedule->addPeriod('14:00', '17:00');

// Multiple periods at once
$schedule->addPeriods([
    ['start_time' => '09:00', 'end_time' => '12:00'],
    ['start_time' => '14:00', 'end_time' => '17:00'],
]);
```

## Metadata

Attach custom data to schedules:

```php
->withMetadata([
    'patient_id' => 1,
    'type' => 'consultation',
    'notes' => 'Follow-up required'
])
```

## Validation Rules

```php
// Prevent overlapping schedules
->noOverlap()

// Allow overlapping (explicit)
->allowOverlap()

// Restrict to working hours
->workingHoursOnly('09:00', '17:00')

// Maximum duration
->maxDuration(120) // 120 minutes

// No weekends
->noWeekends()
```

## Schedule State

```php
// Create inactive schedule
->inactive()

// Explicitly set as active (default)
->active()
```

## Conflict Detection

```php
use Zap\Facades\Zap;

// Find conflicts for a schedule
$conflicts = Zap::findConflicts($schedule);

// Check if schedule has conflicts
$hasConflicts = Zap::hasConflicts($schedule);

// Check via model
$hasConflict = $doctor->hasScheduleConflict($schedule);
$conflicts = $doctor->findScheduleConflicts($schedule);
```

## Schedule Model Properties

```php
$schedule->isAvailability();  // Check if availability type
$schedule->isAppointment();   // Check if appointment type
$schedule->isBlocked();       // Check if blocked type
$schedule->isCustom();        // Check if custom type
$schedule->preventsOverlaps(); // Check if prevents overlaps
$schedule->allowsOverlaps();   // Check if allows overlaps
$schedule->isActiveOn($date);  // Check if active on date
$schedule->total_duration;     // Get total duration in minutes
```

## Configuration

Key settings in `config/zap.php`:

```php
'default_rules' => [
    'no_overlap' => [
        'enabled' => true,
        'applies_to' => ['appointment', 'blocked'],
    ],
],

'conflict_detection' => [
    'enabled' => true,
    'buffer_minutes' => 0,
],

'time_slots' => [
    'buffer_minutes' => 0,
],

'validation' => [
    'require_future_dates' => true,
    'max_date_range' => 365,
    'min_period_duration' => 15,
    'max_periods_per_schedule' => 50,
],
```
