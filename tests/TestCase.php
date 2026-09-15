<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Tests;

use Bpmore\Fallow\ServiceProvider;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Testing\AddonTestCase;

/**
 * A booted Statamic reading the fixture site under tests/__fixtures__.
 *
 * Statamic's harness already points every content store at that folder.
 * Blueprints and sites are not stores, so they are pointed there here. The
 * suite never saves anything, so nothing guards the fixture files from
 * writes: an analyzer that wrote content would be a bug the fixtures should
 * show, not hide.
 */
abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Testbench ships no app key, and the control panel encrypts sessions.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Three sites is a Pro feature, and the entry store only reads a
        // site handle from the file path when multisite is on.
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.system.multisite', true);

        $app['config']->set('statamic.system.blueprints_path', __DIR__.'/__fixtures__/resources/blueprints');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_US'],
            'fr' => ['name' => 'French', 'url' => '/fr', 'locale' => 'fr_FR'],
            'fr-CA' => ['name' => 'French (Canada)', 'url' => '/fr-ca', 'locale' => 'fr_CA'],
        ]);

        // Statamic's stache walks a folder through one shared traverser. An
        // entry store whose collection is not loaded yet loads it from inside
        // its own file filter, and that nested walk swaps the traverser's
        // filter out from under the outer one, so every entry file after the
        // first is dropped. Loading the collections first, as the auditor
        // itself does, avoids that.
        Collection::all();
    }
}
