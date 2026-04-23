<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        $hasLeadingPlus = str_starts_with($trimmed, '+');
        $digitsOnly = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digitsOnly === '') {
            return null;
        }

        return $hasLeadingPlus ? '+' . $digitsOnly : $digitsOnly;
    }
}
