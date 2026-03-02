<?php

declare(strict_types=1);

namespace App\Services\Autoplan;

use App\Facades\Settings;
use Illuminate\Support\Facades\Log;

final class AutoplanSettings
{
    public function minRestMinutes(): int
    {
        $minutes = Settings::getInt('autoplan.min_rest_minutes', 0);

        if ($minutes > 0) {
            return $minutes;
        }

        $legacyHours = Settings::get('autoplan.min_rest_hours');

        if (is_numeric($legacyHours)) {
            Log::warning('autoplan.settings.legacy_min_rest_hours_used', [
                'legacy_hours' => (int) $legacyHours,
                'resolved_minutes' => (int) $legacyHours * 60,
            ]);

            return (int) $legacyHours * 60;
        }

        return 660;
    }
}
