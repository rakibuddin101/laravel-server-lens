<?php

namespace Rakib\ServerLens\Services;

class GeoLocationService
{
    private ?object $reader = null;

    public function lookup(string $ip): array
    {
        $null = ['country_code' => null, 'country_name' => null, 'city' => null];

        if (config('server-lens.geo.driver', 'none') !== 'maxmind') {
            return $null;
        }

        try {
            $reader = $this->reader();

            if ($reader === null) {
                return $null;
            }

            $record = $reader->city($ip);

            return [
                'country_code' => $record->country->isoCode ?? null,
                'country_name' => $record->country->name ?? null,
                'city'         => $record->city->name ?? null,
            ];
        } catch (\Throwable) {
            return $null;
        }
    }

    private function reader(): ?object
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        $path = config('server-lens.geo.mmdb_path', '');

        if (!$path || !file_exists($path)) {
            return null;
        }

        if (!class_exists('\GeoIp2\Database\Reader')) {
            return null;
        }

        $this->reader = new \GeoIp2\Database\Reader($path);

        return $this->reader;
    }
}
