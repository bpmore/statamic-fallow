<?php

declare(strict_types=1);

use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Facades\Utility;

/*
 * Spec section 7.
 */

beforeEach(function () {
    $this->super = User::make()->email('super@example.test')->makeSuper();
    $this->super->save();
});

afterEach(function () {
    // Users are files under the fixture folder; leave nothing behind.
    User::all()->each->delete();
    Role::all()->each->delete();
});

function fallowPage(string $query = ''): string
{
    $response = test()->actingAs(test()->super)->get(cp_route('utilities.index').'/fallow'.($query ? '?'.$query : ''))->assertOk()->getContent();

    preg_match('/data-page="([^"]+)"/', $response, $m);
    $page = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);

    return html_entity_decode((string) $page['props']['html'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

test('the utility is registered with its handle, titles, description, and an icon', function () {
    $utility = Utility::boot()->find('fallow');

    expect($utility)->not->toBeNull()
        ->and($utility->title())->toBe('Fallow')
        ->and($utility->navTitle())->toBe('Fallow')
        ->and($utility->description())->toBe('Find blueprint fields your content never uses.')
        ->and($utility->icon())->not->toBeEmpty();
});

test('the page renders the summary, a table per blueprint, and the orphan list', function () {
    $html = fallowPage();

    expect($html)->toContain('pages / default')
        ->and($html)->toContain('products / default')
        ->and($html)->toContain('articles / post')
        ->and($html)->toContain('articles / note')
        ->and($html)->toContain('blocks[hero].cta')
        ->and($html)->toContain('legacy_teaser')
        ->and($html)->toContain('never instantiated')
        ->and($html)->not->toContain('<form')
        ->and($html)->not->toContain('type="submit"');
});

test('?collection= narrows the page to one collection', function () {
    $html = fallowPage('collection=products');

    expect($html)->toContain('products / default')
        ->and($html)->not->toContain('pages / default');
});

test('an unknown ?collection= says so instead of analyzing', function () {
    $html = fallowPage('collection=nope');

    expect($html)->toContain('Unknown collection')
        ->and($html)->not->toContain('pages / default');
});

test('above the entry limit the page points at the command and does not analyze', function () {
    // Nine localizations across the fixture, three in pages.
    config()->set('fallow.cp_entry_limit', 3);

    $html = fallowPage();

    expect($html)->toContain('Too many entries')
        ->and($html)->toContain('php please fallow:audit')
        ->and($html)->not->toContain('pages / default');

    // Narrowed to one small collection, it analyzes again.
    expect(fallowPage('collection=pages'))->toContain('pages / default');
});

test('a user without the permission cannot open the page', function () {
    Role::make('editor')->permissions(['access cp'])->save();
    $user = User::make()->email('editor@example.test')->assignRole('editor');
    $user->save();

    // Statamic turns the permission failure into a redirect to the
    // dashboard with an error, not a bare 403.
    $this->actingAs($user)->get(cp_route('utilities.index').'/fallow')->assertRedirect(cp_route('index'));
});

test('a role with the permission opens the page', function () {
    Role::make('auditor')->permissions(['access cp', 'access fallow utility'])->save();
    $user = User::make()->email('auditor@example.test')->assignRole('auditor');
    $user->save();

    $this->actingAs($user)->get(cp_route('utilities.index').'/fallow')->assertOk();
});
