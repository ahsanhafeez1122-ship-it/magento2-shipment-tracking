<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model\Webhook;

/**
 * Verifies the X-Tracking-Signature header: "t=<unix time>,v1=<hex hmac-sha256 of "t.body">".
 * Same scheme as Stripe webhooks: the timestamp is signed too, so old requests can't be replayed.
 */
class SignatureVerifier
{
    public function verify(string $header, string $body, string $secret, int $now, int $toleranceSeconds = 300): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = strtolower($value);
            }
        }

        if ($timestamp === null || $signatures === [] || abs($now - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = self::sign($timestamp, $body, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public static function sign(int $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }
}
