<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Tests;

use Bpmore\Fallow\ServiceProvider;
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

        // Three sites is a Pro feature.
        $app['config']->set('statamic.editions.pro', true);

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
    }
}
