<?php

namespace App\Support;

class Phone
{
    /** Normalise Pakistani mobile numbers to 03XXXXXXXXX. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($d, '0092')) {
            $d = substr($d, 4);
        } elseif (str_starts_with($d, '92')) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 10 && str_starts_with($d, '3')) {
            $d = '0'.$d;
        }
        return $d;
    }

    public static function isValidMobile(?string $normalized): bool
    {
        return $normalized !== null && preg_match('/^03\d{9}$/', $normalized) === 1;
    }
}
