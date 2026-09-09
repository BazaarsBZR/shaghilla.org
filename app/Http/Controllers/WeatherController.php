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
            'jbeil' => 'جبيل',
            'jezzine' => 'جزين',
            'jounieh' => 'جونية',
            'nabatieh' => 'النبطية',
            'saida' => 'صيدا',
            'sidon' => 'صيدا',
            'tripoli' => 'طرابلس',
            'tyre' => 'صور',
            'zahle' => 'زحلة',
        ][$normalized] ?? mb_substr(trim($city), 0, 40);
    }
}
