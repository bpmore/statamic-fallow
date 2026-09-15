<?php

declare(strict_types=1);

use Bpmore\Fallow\Analysis\BlueprintWalker;
use Bpmore\Fallow\Analysis\FieldNode;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;

function flatPaths(array $nodes): array
{
    return array_map(fn (FieldNode $n) => [$n->path, $n->type, $n->depth], array_merge(...array_map(fn (FieldNode $n) => $n->flatten(), $nodes)));
}

test('walks the pages blueprint into paths with types and depths, in declaration order', function () {
    $nodes = (new BlueprintWalker)->walk(Collection::findByHandle('pages')->entryBlueprint('default'));

    expect(flatPaths($nodes))->toBe([
        ['title', 'text', 0],
        ['subtitle', 'text', 0],
        ['promo', 'text', 0],
        ['featured', 'toggle', 0],
        ['links', 'grid', 0],
        ['links.label', 'text', 1],
        ['links.url', 'text', 1],
        ['blocks', 'replicator', 0],
        ['blocks[hero].heading', 'text', 1],
        ['blocks[hero].cta', 'text', 1],
        ['blocks[quote].body', 'textarea', 1],
        ['body', 'bard', 0],
    ]);
});

test('skips the slug field Statamic adds, because the slug is the filename', function () {
    $blueprint = Collection::findByHandle('pages')->entryBlueprint('default');

    expect($blueprint->hasField('slug'))->toBeTrue()
        ->and(array_column(flatPaths((new BlueprintWalker)->walk($blueprint)), 0))->not->toContain('slug');
});

test('reads the older flat sets shape and nests to any depth', function () {
    $blueprint = Blueprint::make()->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'blocks', 'field' => ['type' => 'replicator', 'sets' => [
            'hero' => ['fields' => [
                ['handle' => 'links', 'field' => ['type' => 'grid', 'fields' => [
                    ['handle' => 'url', 'field' => ['type' => 'text']],
                ]]],
                ['handle' => 'meta', 'field' => ['type' => 'group', 'fields' => [
                    ['handle' => 'inner', 'field' => ['type' => 'bard', 'sets' => ['g' => ['sets' => ['q' => ['fields' => [
                        ['handle' => 'body', 'field' => ['type' => 'textarea']],
                    ]]]]]]],
                ]]],
            ]],
        ]]],
    ]]]]]]);

    expect(flatPaths((new BlueprintWalker)->walk($blueprint)))->toBe([
        ['blocks', 'replicator', 0],
        ['blocks[hero].links', 'grid', 1],
        ['blocks[hero].links.url', 'text', 2],
        ['blocks[hero].meta', 'group', 1],
        ['blocks[hero].meta.inner', 'bard', 2],
        ['blocks[hero].meta.inner[q].body', 'textarea', 3],
    ]);
});

test('skips a date field only in a dated collection', function () {
    $blueprint = fn () => Blueprint::make()->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'date', 'field' => ['type' => 'date']],
        ['handle' => 'published_on', 'field' => ['type' => 'date']],
    ]]]]]]);

    expect(array_column(flatPaths((new BlueprintWalker)->walk($blueprint(), dated: true)), 0))->toBe(['published_on'])
        ->and(array_column(flatPaths((new BlueprintWalker)->walk($blueprint(), dated: false)), 0))->toBe(['date', 'published_on']);
});

test('a replicator with no sets and a grid with no fields are leaves', function () {
    $blueprint = Blueprint::make()->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'a', 'field' => ['type' => 'replicator']],
        ['handle' => 'b', 'field' => ['type' => 'grid']],
    ]]]]]]);

    expect(flatPaths((new BlueprintWalker)->walk($blueprint)))->toBe([
        ['a', 'replicator', 0],
        ['b', 'grid', 0],
    ]);
});
