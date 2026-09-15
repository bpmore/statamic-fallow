<?php

declare(strict_types=1);

namespace Bpmore\Fallow;

use Statamic\Providers\AddonServiceProvider;

/**
 * The addon: a config file, one command, and one read-only utility page.
 *
 * Everything that reads content lives in the Analysis namespace and is
 * reached from here only through the command and the utility.
 */
class ServiceProvider extends AddonServiceProvider
{
    public const PACKAGE = 'bpmore/statamic-fallow';

    /**
     * Merged by hand rather than by the parent. The parent names the config
     * file after the addon slug, which is `statamic-fallow` to match the
     * other addons in this family, while the file is `config/fallow.php` and
     * is read as `config('fallow.*')`.
     */
    protected $config = false;

    protected $commands = [
        Commands\AuditCommand::class,
    ];

    protected $viewNamespace = 'fallow';

    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/fallow.php', 'fallow');
    }

    public function bootAddon()
    {
        $this->publishes([
            __DIR__.'/../config/fallow.php' => config_path('fallow.php'),
        ], 'fallow-config');
    }
}
