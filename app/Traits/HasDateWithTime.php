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

    /**
     * Serialize dates in the app/tenant timezone as a plain "Y-m-d H:i:s" string.
     *
     * Laravel's default appends a `Z` (UTC marker), which makes the frontend
     * timezone-shift the value — a transaction saved at e.g. 22:43 Beirut then
     * renders as the next calendar day in any view that converts to local time,
     * while a view that slices the date part shows the picked day. These are
     * calendar/transaction dates, not UTC instants, so we emit them verbatim in
     * the tenant timezone (config('app.timezone'), set per-tenant on boot) with
     * no `Z`, so every view renders the same day.
     */
    public function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
