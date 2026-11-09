<?php
declare(strict_types=1);

namespace Plaxswap\WooCommerce\Core;

/** Exact decimal strings only. These operations never convert money to floats. */
final class Decimal
{
    public static function normalize($value): string
    {
        if (!is_string($value) || strlen($value) > 80 || !preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $value)) {
            throw new \InvalidArgumentException('invalid_decimal');
        }

        $parts = explode('.', $value, 2);
        $whole = ltrim($parts[0], '0');
        $fraction = isset($parts[1]) ? rtrim($parts[1], '0') : '';

        return ($whole === '' ? '0' : $whole) . ($fraction === '' ? '' : '.' . $fraction);
    }

    public static function equal($left, $right): bool
    {
        try {
            return self::normalize($left) === self::normalize($right);
        } catch (\InvalidArgumentException $error) {
            return false;
        }
    }

    public static function positive($value): bool
    {
        try {
            return self::normalize($value) !== '0';
        } catch (\InvalidArgumentException $error) {
            return false;
        }
    }

    /** The fiat pricing codes currently supported by the Plaxswap gateway. */
    public static function currencySupported(string $currency): bool
    {
        return in_array($currency, explode(' ', 'USD EUR GBP BRL MXN ARS NGN ZAR JPY AED AUD BDT BGN BHD BOB CAD CHF CLP CNY COP CRC CZK DKK DOP EGP GEL GHS GTQ HKD HUF IDR ILS INR ISK JMD KES KRW KWD KZT LKR MAD MYR NOK NZD OMR PEN PHP PKR PLN PYG QAR RON RSD SAR SEK SGD THB TRY TTD TWD TZS UAH UGX UYU VND XAF XOF'), true);
    }
}
