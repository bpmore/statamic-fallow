<?php

declare(strict_types=1);

use Bpmore\Fallow\Weather\ContentModelContributor;
use Bpmore\SiteWeather\Contracts\WeatherContributor;
use Bpmore\SiteWeather\Contributors;
use Bpmore\SiteWeather\Forecast;
use Bpmore\SiteWeather\State;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\Utility;

/**
 * The band through Site Weather's real contract. Site Weather is a suggestion,
 * not a dependency, and this repository keeps no lock file, so it is only
 * present when a developer has installed it locally; without it these tests
 * skip and say so. The decision itself is tested in tests/Unit regardless.
 */
$siteWeatherInstalled = interface_exists(WeatherContributor::class);

beforeEach(function () {
    $this->reportPath = sys_get_temp_dir().'/fallow-weather-'.uniqid().'/audit.json';
    config()->set('fallow.report_path', $this->reportPath);
});

afterEach(function () {
    if (is_file($this->reportPath)) {
        unlink($this->reportPath);
        rmdir(dirname($this->reportPath));
    }
});

function contentModelBand(): \Bpmore\SiteWeather\Reading
{
    return app(ContentModelContributor::class)->reading();
}

test('the contributor is tagged for Site Weather to find', function () {
    $tagged = collect(app()->tagged('site-weather.contributors'));

    expect($tagged)->toHaveCount(1)
        ->and($tagged->first())->toBeInstanceOf(ContentModelContributor::class)
        ->and($tagged->first()->key())->toBe('content-model')
        ->and($tagged->first()->label())->toBe('Content model');
})->skip(! $siteWeatherInstalled, 'Site Weather is not installed');

test('unknown, with the command to run, until a report has been saved', function () {
    $reading = contentModelBand();

    expect($reading->state)->toBe(State::Unknown)
        ->and($reading->headline)->toStartWith('No audit saved yet: run php please fallow:audit --json=')
        ->and($reading->url)->toBe(Utility::find('fallow')?->url())
        ->and($reading->computedAt)->toBeNull();
})->skip(! $siteWeatherInstalled, 'Site Weather is not installed');

test('the instruction names the path relative to the site root', function () {
    config()->set('fallow.report_path', base_path('storage/app/fallow/audit.json'));

    expect(contentModelBand()->headline)->toBe('No audit saved yet: run php please fallow:audit --json=storage/app/fallow/audit.json');
})->skip(! $siteWeatherInstalled, 'Site Weather is not installed');

test('unknown when the report path is turned off', function () {
    config()->set('fallow.report_path', null);

    expect(contentModelBand()->state)->toBe(State::Unknown)
        ->and(contentModelBand()->headline)->toBe('No report path configured');
})->skip(! $siteWeatherInstalled, 'Site Weather is not installed');

test('unknown when the saved report cannot be read', function () {
    mkdir(dirname($this->reportPath), 0755, true);
    file_put_contents($this->reportPath, '{not json');

    expect(contentModelBand()->state)->toBe(State::Unknown)
        ->and(contentModelBand()->headline)->toBe('The saved audit could not be read');
})->skip(! $siteWeatherInstalled, 'Site Weather is not installed');

test('reads a saved report, dated to when it was generated', function () {
    mkdir(dirname($this->reportPath), 0755, true);
    file_put_contents($this->reportPath, json_encode([
        'generated_at' => '2026-09-15T09:30:00+00:00',
        'summary' => ['fields_total' => 80, 'healthy' => 68, 'sparse' => 0, 'dead' => 12, 'never_instantiated' => 0, 'orphan_keys' => 0],
    ]));

    $reading = contentModelBand();

    expect($reading->state)->toBe(State::Rain)
        ->and($reading->headline)->toBe('12 of 80 fields dead')
        ->and($reading->computedAt?->toIso8601String())->toBe('2026-09-15T09:30:00+00:00');
})->skip(! $siteWeatherInstalled, 'Site Weather is not installed');

test('reaches the tile end to end from a real saved audit', function () {
    Artisan::call('statamic:fallow:audit', ['--json' => $this->reportPath, '--collection' => ['pages']]);

    $forecast = Forecast::from(app(Contributors::class));

    expect($forecast->bands)->toHaveCount(1)
        ->and($forecast->bands[0]->key)->toBe('content-model')
        ->and($forecast->overall())->not->toBe(State::Unknown)
        ->and($forecast->bands[0]->reading->computedAt)->not->toBeNull()
        ->and($forecast->summary())->toStartWith('Overall: ');
})->skip(! $siteWeatherInstalled, 'Site Weather is not installed');
