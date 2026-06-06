<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

final class CustomerNormalizer
{
    public static function email(string $email): string
    {
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    public static function whatsapp(string $number, string $defaultCountryCode = '+62'): string
    {
        $number = trim($number);
        if ($number === '') {
            return '';
        }

        $digits = '';
        $length = strlen($number);
        for ($i = 0; $i < $length; $i++) {
            $char = $number[$i];
            if ($char >= '0' && $char <= '9') {
                $digits .= $char;
            }
        }

        if ($digits === '') {
            return '';
        }

        $country = '';
        $ccLength = strlen($defaultCountryCode);
        for ($i = 0; $i < $ccLength; $i++) {
            $char = $defaultCountryCode[$i];
            if ($char >= '0' && $char <= '9') {
                $country .= $char;
            }
        }

        $country = $country !== '' ? $country : '62';

        if (str_starts_with($digits, '0')) {
            $digits = $country . substr($digits, 1);
        }

        if (!str_starts_with($digits, $country) && strlen($digits) <= 12) {
            $digits = $country . $digits;
        }

        return $digits;
    }
}
