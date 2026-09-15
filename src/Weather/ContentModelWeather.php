<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Weather;

/**
 * What a saved audit says about the content model, as weather.
 *
 * Framework-free and independent of Site Weather on purpose: the decision is
 * this addon's, it is the part worth testing hardest, and it must be testable
 * without Site Weather installed. The contributor maps the result onto Site
 * Weather's types.
 *
 * The scale, from the audit's own vocabulary. Dead fields are blueprint
 * fields no entry fills; sparse ones are filled in fewer than the minimum
 * share of places; orphan keys are data in entries that no field claims.
 * Fields that were never instantiated are left out of the share, because
 * the audit could not judge them:
 *
 *   clear     every judged field in use, no orphan keys
 *   fair      some sparse fields, nothing dead, no orphan keys
 *   overcast  a dead field, or a quarter of fields sparse
 *   rain      a tenth of fields dead, or orphan keys in entries
 *   storm     a quarter of fields dead, or a tenth dead alongside orphan keys
 */
final class ContentModelWeather
{
    public const DEAD_RAIN_SHARE = 0.10;

    public const DEAD_STORM_SHARE = 0.25;

    public const SPARSE_OVERCAST_SHARE = 0.25;

    /**
     * @param  array<string, int>  $summary  the report's `summary` block
     * @return array{state: string, headline: string}
     */
    public static function decide(array $summary): array
    {
        $dead = (int) ($summary['dead'] ?? 0);
        $sparse = (int) ($summary['sparse'] ?? 0);
        $orphans = (int) ($summary['orphan_keys'] ?? 0);
        $judged = (int) ($summary['fields_total'] ?? 0) - (int) ($summary['never_instantiated'] ?? 0);

        if ($dead === 0 && $sparse === 0 && $orphans === 0) {
            return ['state' => 'clear', 'headline' => sprintf('All %s in use', self::fields($judged))];
        }

        $deadShare = $judged > 0 ? $dead / $judged : 0.0;
        $sparseShare = $judged > 0 ? $sparse / $judged : 0.0;

        $state = match (true) {
            $deadShare >= self::DEAD_STORM_SHARE => 'storm',
            $deadShare >= self::DEAD_RAIN_SHARE && $orphans > 0 => 'storm',
            $deadShare >= self::DEAD_RAIN_SHARE => 'rain',
            $orphans > 0 => 'rain',
            $dead > 0 => 'overcast',
            $sparseShare >= self::SPARSE_OVERCAST_SHARE => 'overcast',
            default => 'fair',
        };

        $parts = [];

        if ($dead > 0) {
            $parts[] = sprintf('%s of %s dead', number_format($dead), self::fields($judged));
        }

        if ($sparse > 0) {
            $parts[] = $dead > 0
                ? sprintf('%s sparse', number_format($sparse))
                : sprintf('%s of %s sparse', number_format($sparse), self::fields($judged));
        }

        if ($orphans > 0) {
            $parts[] = sprintf('%s orphan %s', number_format($orphans), $orphans === 1 ? 'key' : 'keys');
        }

        return ['state' => $state, 'headline' => implode(', ', $parts)];
    }

    private static function fields(int $count): string
    {
        return number_format($count).' '.($count === 1 ? 'field' : 'fields');
    }
}
