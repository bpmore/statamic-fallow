<?php

declare(strict_types=1);

namespace Bpmore\Fallow;

use Bpmore\Fallow\Analysis\Auditor;
use Bpmore\Fallow\Analysis\BlueprintWalker;
use Bpmore\Fallow\Analysis\Scope;
use Bpmore\Fallow\Analysis\UnknownScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Statamic\Facades\Collection;
use Statamic\Facades\Utility;
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

        $this->app->bind(Auditor::class, fn () => new Auditor(
            new BlueprintWalker,
            array_map('strval', (array) config('fallow.ignored_keys', [])),
        ));
    }

    public function bootAddon()
    {
        $this->publishes([
            __DIR__.'/../config/fallow.php' => config_path('fallow.php'),
        ], 'fallow-config');

        // `@plain($x)` for any value the utility view prints. See VueSafe
        // for why Blade's own escaping is not enough there.
        Blade::directive('plain', fn ($expression) => "<?php echo \\Bpmore\\Fallow\\Support\\VueSafe::text({$expression}); ?>");

        // Registering a utility gives it an `access fallow utility`
        // permission of its own, and the route is gated by that. The page
        // is read-only, so that one permission is the whole of its access
        // control.
        Utility::extend(fn () => Utility::register('fallow')
            ->title('Fallow')
            ->navTitle('Fallow')
            ->icon('nature-leaf')
            ->description('Find blueprint fields your content never uses.')
            ->view('fallow::utility', fn (Request $request) => $this->utilityData($request)));
    }

    /**
     * The audit, run while the page loads, unless the site is too big for
     * that. Spec section 7.
     *
     * @return array<string, mixed>
     */
    private function utilityData(Request $request): array
    {
        $collection = $request->query('collection');
        $collection = is_string($collection) && $collection !== '' ? $collection : null;

        $collections = Collection::all()->map->handle()->sort()->values()->all();
        $limit = (int) config('fallow.cp_entry_limit', 5000);

        $data = [
            'collections' => $collections,
            'collection' => $collection,
            'limit' => $limit,
            'unknown' => null,
            'tooMany' => null,
            'report' => null,
        ];

        if ($collection !== null && ! in_array($collection, $collections, true)) {
            return ['unknown' => $collection] + $data;
        }

        $auditor = $this->app->make(Auditor::class);
        $scope = new Scope($collection === null ? [] : [$collection], [], (float) config('fallow.min_fill', 0.05));

        try {
            $count = $auditor->countEntries($scope);

            if ($count > $limit) {
                return ['tooMany' => $count] + $data;
            }

            return ['report' => $auditor->audit($scope)->toArray()] + $data;
        } catch (UnknownScope $e) {
            return ['unknown' => $e->getMessage()] + $data;
        }
    }
}
