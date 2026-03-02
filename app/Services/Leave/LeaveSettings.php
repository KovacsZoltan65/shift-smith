<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Facades\Settings;

final class LeaveSettings
{
    public static function ageBonusMinutes(int $age): int
    {
        $table = Settings::get('leave.annual.age_bonus_table', []);

        if (! is_array($table) || $table === []) {
            return 0;
        }

        $matchedMinutes = 0;
        $matchedAgeFrom = null;

        foreach ($table as $row) {
            if (! is_array($row)) {
                continue;
            }

            $ageFrom = $row['age_from'] ?? null;
            $minutes = $row['extra_minutes_per_year'] ?? null;

            if (! is_numeric($ageFrom) || ! is_numeric($minutes)) {
                continue;
            }

            $ageFrom = (int) $ageFrom;
            $minutes = max(0, (int) $minutes);

            if ($ageFrom > $age) {
                continue;
            }

            if ($matchedAgeFrom === null || $ageFrom > $matchedAgeFrom) {
                $matchedAgeFrom = $ageFrom;
                $matchedMinutes = $minutes;
            }
        }

        return $matchedMinutes;
    }

    public static function baseMinutes(): int
    {
        return Settings::getInt('leave.annual.base_minutes', 0);
    }

    public static function minutesPerDay(): int
    {
        return Settings::getInt('leave.minutes_per_day', 480);
    }
}
