<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->boolean('areas')) {
            return $this->lebanonAreas();
        }

        $label = SiteSetting::getValue('header_weather_label_ar', 'لبنان');

        $lat = (float) (SiteSetting::getValue('header_weather_lat', '33.8938') ?? 33.8938);
        $lon = (float) (SiteSetting::getValue('header_weather_lon', '35.5018') ?? 35.5018);

        if (
            strtoupper((string) $request->query('country')) === 'LB'
            && is_numeric($request->query('latitude'))
            && is_numeric($request->query('longitude'))
        ) {
            $candidateLat = (float) $request->query('latitude');
            $candidateLon = (float) $request->query('longitude');

            if ($candidateLat >= 33.0 && $candidateLat <= 35.0 && $candidateLon >= 35.0 && $candidateLon <= 37.0) {
                $lat = $candidateLat;
                $lon = $candidateLon;
                $label = $this->arabicCityLabel((string) $request->query('city')) ?: $label;
            }
        }

        if ($lat < -90 || $lat > 90) {
            $lat = 33.8938;
        }
        if ($lon < -180 || $lon > 180) {
            $lon = 35.5018;
        }

        $cacheKey = 'weather.header.v3.'.md5(round($lat, 2).','.round($lon, 2));

        $payload = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($lat, $lon): array {
            try {
                $response = Http::timeout(10)
                    ->acceptJson()
                    ->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'current_weather' => true,
                        'temperature_unit' => 'celsius',
                        'timezone' => 'Asia/Beirut',
                    ]);

                if (! $response->ok()) {
                    return [
                        'temperature_c' => null,
                        'weather_code' => null,
                    ];
                }

                $data = $response->json();

                $temperature = data_get($data, 'current_weather.temperature');
                if ($temperature === null) {
                    $temperature = data_get($data, 'current.temperature_2m');
                }

                $code = data_get($data, 'current_weather.weathercode');
                if ($code === null) {
                    $code = data_get($data, 'current.weather_code');
                }

                return [
                    'temperature_c' => is_numeric($temperature) ? (float) $temperature : null,
                    'weather_code' => is_numeric($code) ? (int) $code : null,
                ];
            } catch (\Throwable) {
                return [
                    'temperature_c' => null,
                    'weather_code' => null,
                ];
            }
        });

        if (! is_numeric($payload['temperature_c'] ?? null)) {
            Cache::forget($cacheKey);
        }

        return response()->json([
            'label' => $label,
            'temperature_c' => $payload['temperature_c'],
            'weather_code' => $payload['weather_code'],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function arabicCityLabel(string $city): ?string
    {
        $normalized = strtolower(trim($city));

        if ($normalized === '') {
            return null;
        }

        return [
            'aley' => 'عاليه',
            'baabda' => 'بعبدا',
            'baalbek' => 'بعلبك',
            'batroun' => 'البترون',
            'beirut' => 'بيروت',
            'bsharri' => 'بشرّي',
            'byblos' => 'جبيل',
            'chtaura' => 'شتورا',
            'halba' => 'حلبا',
            'hermel' => 'الهرمل',
            'jbeil' => 'جبيل',
            'jezzine' => 'جزين',
            'jounieh' => 'جونية',
            'nabatieh' => 'النبطية',
            'saida' => 'صيدا',
            'sidon' => 'صيدا',
            'tripoli' => 'طرابلس',
            'tyre' => 'صور',
            'zahle' => 'زحلة',
            'zgharta' => 'زغرتا',
        ][$normalized] ?? mb_substr(trim($city), 0, 40);
    }

    private function lebanonAreas(): JsonResponse
    {
        $areas = [
            ['key' => 'beirut', 'label' => 'بيروت', 'latitude' => 33.8938, 'longitude' => 35.5018],
            ['key' => 'tripoli', 'label' => 'طرابلس', 'latitude' => 34.4335, 'longitude' => 35.8441],
            ['key' => 'halba', 'label' => 'حلبا - عكار', 'latitude' => 34.5428, 'longitude' => 36.0791],
            ['key' => 'zgharta', 'label' => 'زغرتا', 'latitude' => 34.3974, 'longitude' => 35.8956],
            ['key' => 'bsharri', 'label' => 'بشرّي', 'latitude' => 34.2509, 'longitude' => 36.0101],
            ['key' => 'batroun', 'label' => 'البترون', 'latitude' => 34.2554, 'longitude' => 35.6580],
            ['key' => 'jbeil', 'label' => 'جبيل', 'latitude' => 34.1236, 'longitude' => 35.6511],
            ['key' => 'jounieh', 'label' => 'جونية', 'latitude' => 33.9808, 'longitude' => 35.6178],
            ['key' => 'baabda', 'label' => 'بعبدا', 'latitude' => 33.8339, 'longitude' => 35.5442],
            ['key' => 'aley', 'label' => 'عاليه', 'latitude' => 33.8086, 'longitude' => 35.5974],
            ['key' => 'zahle', 'label' => 'زحلة', 'latitude' => 33.8463, 'longitude' => 35.9020],
            ['key' => 'chtaura', 'label' => 'شتورا', 'latitude' => 33.8144, 'longitude' => 35.8539],
            ['key' => 'baalbek', 'label' => 'بعلبك', 'latitude' => 34.0058, 'longitude' => 36.2181],
            ['key' => 'hermel', 'label' => 'الهرمل', 'latitude' => 34.3948, 'longitude' => 36.3846],
            ['key' => 'saida', 'label' => 'صيدا', 'latitude' => 33.5631, 'longitude' => 35.3689],
            ['key' => 'jezzine', 'label' => 'جزين', 'latitude' => 33.5417, 'longitude' => 35.5844],
            ['key' => 'nabatieh', 'label' => 'النبطية', 'latitude' => 33.3772, 'longitude' => 35.4838],
            ['key' => 'tyre', 'label' => 'صور', 'latitude' => 33.2705, 'longitude' => 35.2038],
        ];

        $weather = Cache::remember('weather.lebanon.areas.v1', now()->addMinutes(15), function () use ($areas): array {
            try {
                $response = Http::timeout(12)
                    ->acceptJson()
                    ->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude' => implode(',', array_column($areas, 'latitude')),
                        'longitude' => implode(',', array_column($areas, 'longitude')),
                        'current_weather' => true,
                        'temperature_unit' => 'celsius',
                        'timezone' => 'Asia/Beirut',
                    ]);

                if (! $response->ok()) {
                    return [];
                }

                $payload = $response->json();
                $payload = array_is_list($payload) ? $payload : [$payload];

                return collect($areas)->map(function (array $area, int $index) use ($payload): array {
                    $temperature = data_get($payload, $index.'.current_weather.temperature');
                    $code = data_get($payload, $index.'.current_weather.weathercode');

                    return [
                        ...$area,
                        'temperature_c' => is_numeric($temperature) ? (float) $temperature : null,
                        'weather_code' => is_numeric($code) ? (int) $code : null,
                    ];
                })->all();
            } catch (\Throwable) {
                return [];
            }
        });

        if ($weather === []) {
            Cache::forget('weather.lebanon.areas.v1');
        }

        return response()->json([
            'areas' => $weather,
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
