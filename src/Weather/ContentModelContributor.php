<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Weather;

use Bpmore\SiteWeather\Contracts\WeatherContributor;
use Bpmore\SiteWeather\Reading;
use Bpmore\SiteWeather\State;
use Illuminate\Support\Carbon;
use Statamic\Facades\Utility;
use Throwable;

/**
 * The Content model band on Site Weather's tile.
 *
 * Site Weather is an optional companion, not a dependency: this class is
 * tagged by name in the service provider and only loaded when Site Weather
 * resolves the tag, so the interface it implements need not exist on a site
 * without it. The decision lives in ContentModelWeather, tested everywhere;
 * this is the mapping onto Site Weather's types.
 *
 * Fallow computes on request and stores nothing, and Site Weather's tile
 * reads stored results only - it must never run an audit on a dashboard
 * load. So this reads the report a site has chosen to save at
 * `fallow.report_path`, and until there is one it says what to run. Fallow
 * itself still writes nothing; the site's scheduled command does.
 */
final class ContentModelContributor implements WeatherContributor
{
    public function key(): string
    {
        return 'content-model';
    }

    public function label(): string
    {
        return 'Content model';
    }

    public function reading(): Reading
    {
        $url = Utility::find('fallow')?->url();
        $path = config('fallow.report_path');

        if (! is_string($path) || $path === '') {
            return Reading::unknown('No report path configured', $url);
        }

        if (! is_file($path)) {
            return Reading::unknown('No audit saved yet: run php please fallow:audit --json='.self::relative($path), $url);
        }

        try {
            $report = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $summary = $report['summary'] ?? null;
            $generatedAt = Carbon::parse((string) ($report['generated_at'] ?? ''));
        } catch (Throwable) {
            return Reading::unknown('The saved audit could not be read', $url);
        }

        if (! is_array($summary)) {
            return Reading::unknown('The saved audit could not be read', $url);
        }

        if ((int) ($summary['fields_total'] ?? 0) - (int) ($summary['never_instantiated'] ?? 0) <= 0) {
            return Reading::unknown('The saved audit judged no fields', $url);
        }

        $decision = ContentModelWeather::decide($summary);

        return new Reading(State::from($decision['state']), $decision['headline'], $url, $generatedAt);
    }

    /** The path as it would be typed from the site root, for the instruction. */
    private static function relative(string $path): string
    {
        $base = rtrim(base_path(), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
