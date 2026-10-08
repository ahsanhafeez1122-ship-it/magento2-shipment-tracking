<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model\Carrier;

/**
 * Reads the "Carriers" textarea from the admin config:
 *   code | Label | https://url/{number} | optional regex | optional, comma, separated, aliases
 */
class CarrierParser
{
    /**
     * @return array{carriers: array<string, Carrier>, errors: string[]}
     */
    public function parse(string $config): array
    {
        $carriers = [];
        $errors = [];

        foreach (preg_split('/\R/', $config) ?: [] as $index => $line) {
            $lineNo = $index + 1;
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            [$code, $label, $url] = array_pad($parts, 3, '');
            $pattern = $parts[3] ?? '';
            $aliases = $parts[4] ?? '';

            $code = strtolower($code);
            if (!preg_match('/^[a-z0-9_]{2,32}$/', $code)) {
                $errors[] = sprintf('Line %d: carrier code must be 2-32 letters, digits or underscores.', $lineNo);
                continue;
            }
            if ($label === '') {
                $errors[] = sprintf('Line %d: label is missing.', $lineNo);
                continue;
            }
            if (!str_starts_with($url, 'https://') || !str_contains($url, '{number}')) {
                $errors[] = sprintf('Line %d: URL must start with https:// and contain {number}.', $lineNo);
                continue;
            }
            if ($pattern !== '' && @preg_match('/' . str_replace('/', '\/', $pattern) . '/', '') === false) {
                $errors[] = sprintf('Line %d: number pattern is not a valid regular expression.', $lineNo);
                continue;
            }
            if (isset($carriers[$code])) {
                $errors[] = sprintf('Line %d: carrier code "%s" is defined twice; the first one is used.', $lineNo, $code);
                continue;
            }

            $aliasList = array_map([Carrier::class, 'normalizeName'], explode(',', $aliases));
            $aliasList[] = Carrier::normalizeName($code);
            $aliasList[] = Carrier::normalizeName($label);

            $carriers[$code] = new Carrier(
                $code,
                $label,
                $url,
                $pattern !== '' ? $pattern : null,
                array_values(array_unique(array_filter($aliasList)))
            );
        }

        return ['carriers' => $carriers, 'errors' => $errors];
    }
}
