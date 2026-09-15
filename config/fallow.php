<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Minimum fill rate
    |---------------------------------------------------------------------------
    |
    | A field filled in fewer than this share of the places it could be filled
    | is reported as sparse. A field filled nowhere is dead, whatever this is
    | set to. The command's --min-fill option overrides it for one run.
    |
    */

    'min_fill' => 0.05,

    /*
    |---------------------------------------------------------------------------
    | Ignored keys
    |---------------------------------------------------------------------------
    |
    | Keys in an entry's own data that are never reported as orphans, on top
    | of the built-in list (id, slug, published, blueprint, the audit stamps,
    | and so on) and any key starting with two underscores. Add keys here
    | that another addon writes to every entry on purpose.
    |
    */

    'ignored_keys' => [],

    /*
    |---------------------------------------------------------------------------
    | Control panel entry limit
    |---------------------------------------------------------------------------
    |
    | The utility page analyzes the site while the page loads. Above this many
    | entry localizations it shows a notice pointing at the command instead,
    | so a large site cannot time out a control panel request.
    |
    */

    'cp_entry_limit' => 5000,

    /*
    |---------------------------------------------------------------------------
    | Saved report, for the Site Weather tile
    |---------------------------------------------------------------------------
    |
    | Fallow computes on request and stores nothing, and Site Weather's tile
    | reads stored results only. If this site saves an audit here - typically
    | by scheduling `php please fallow:audit --json=storage/app/fallow/audit.json`
    | - the tile shows a Content model band read from it. Until then the band
    | says so. Set to null to keep Fallow off the tile entirely.
    |
    */

    'report_path' => storage_path('app/fallow/audit.json'),
];
