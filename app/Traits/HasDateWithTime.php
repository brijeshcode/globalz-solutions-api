<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasDateWithTime
{
    /**
     * Set date attribute with current time
     * If only date is provided (without time), append current time
     *
     * @param string $value
     * @return void
     */
    public function setDateAttribute($value)
    {
        if (!$value) {
            $this->attributes['date'] = null;
            return;
        }

        // Parse the input value
        $date = Carbon::parse($value);

        // Check if time component is midnight (00:00:00)
        // This indicates only date was provided without time
        if ($date->format('H:i:s') === '00:00:00') {
            // Get current time
            $now = Carbon::now();

            // Set the date with current time
            $date->setTime($now->hour, $now->minute, $now->second);
        }

        $this->attributes['date'] = $date;
    }
}
