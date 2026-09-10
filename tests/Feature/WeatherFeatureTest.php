<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherFeatureTest extends TestCase
{
    public function test_lebanon_area_weather_is_returned_in_one_batched_request(): void
    {
        Cache::flush();
        Http::fake([
            'api.open-meteo.com/*' => Http::response(array_map(
                fn (int $index): array => ['current_weather' => ['temperature' => 18 + $index, 'weathercode' => $index % 4]],
                range(0, 17),
            )),
        ]);

        $this->getJson('/weather-feed?areas=1')
            ->assertOk()
            ->assertJsonCount(18, 'areas')
            ->assertJsonPath('areas.0.label', 'بيروت')
            ->assertJsonPath('areas.17.label', 'صور')
            ->assertJsonPath('areas.0.temperature_c', 18);

        Http::assertSentCount(1);
    }
}
