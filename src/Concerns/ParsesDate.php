<?php

namespace QuickBooks\SDK\Concerns;

use Carbon\Carbon;

trait ParsesDate
{
    protected function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) || is_numeric($value)) {
            return Carbon::parse($value);
        }

        return null;
    }
}
