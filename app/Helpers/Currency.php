<?php

declare(strict_types=1);

namespace App\Helpers;

class Currency
{
    private static array $formats = [
        'IDR' => ['symbol' => 'Rp',  'decimals' => 0,  'dec_sep' => ',', 'thou_sep' => '.', 'prefix' => true],
        'USD' => ['symbol' => '$',   'decimals' => 2,  'dec_sep' => '.', 'thou_sep' => ',', 'prefix' => true],
        'SGD' => ['symbol' => 'S$',  'decimals' => 2,  'dec_sep' => '.', 'thou_sep' => ',', 'prefix' => true],
        'AUD' => ['symbol' => 'A$',  'decimals' => 2,  'dec_sep' => '.', 'thou_sep' => ',', 'prefix' => true],
    ];

    public static function code(string $currency = 'IDR'): string
    {
        $currency = strtoupper(trim($currency));
        return isset(self::$formats[$currency]) ? $currency : 'IDR';
    }

    public static function format(float $amount, string $currency = 'IDR'): string
    {
        $cfg = self::$formats[self::code($currency)];
        $formatted = number_format($amount, $cfg['decimals'], $cfg['dec_sep'], $cfg['thou_sep']);
        return $cfg['prefix'] ? $cfg['symbol'] . $formatted : $formatted . ' ' . $cfg['symbol'];
    }

    public static function contextLabel(string $currency = 'IDR', bool $global = false): string
    {
        $code = self::code($currency);
        return $global ? "Global ({$code})" : $code;
    }

    public static function fieldLabel(string $field, string $currency = 'IDR', bool $global = false): string
    {
        $suffix = self::contextLabel($currency, $global);
        return "{$field} ({$suffix})";
    }
}
