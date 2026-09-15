<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class AuditCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:fallow:audit';

    protected $description = 'Report which blueprint fields the content fills, which are dead, and which entry keys no field claims.';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
