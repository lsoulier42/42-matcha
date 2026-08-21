<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reverse geocoding via Nominatim (OpenStreetMap) — free, no API key.
 * Returns the city/town name for a coordinate pair, or null when the
 * lookup fails (offline, rate-limited, unknown area). Per Nominatim
 * usage policy, a descriptive User-Agent is sent on every request.
 */
final class ReverseGeocoder
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    public function reverse(float $lat, float $lng): ?string
    {
        $url = self::ENDPOINT . '?format=jsonv2&zoom=10&addressdetails=1'
            . '&lat=' . rawurlencode((string) $lat)
            . '&lon=' . rawurlencode((string) $lng);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT => 'Matcha/1.0 (42 school project — geolocation)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            return null;
        }

        $data = json_decode($body, true);
        $address = is_array($data) ? ($data['address'] ?? null) : null;
        if (!is_array($address)) {
            return null;
        }

        foreach (['city', 'town', 'village', 'municipality', 'county'] as $key) {
            if (isset($address[$key]) && is_string($address[$key]) && $address[$key] !== '') {
                return $address[$key];
            }
        }

        return null;
    }
}
