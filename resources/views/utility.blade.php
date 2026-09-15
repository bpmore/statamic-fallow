{{--
    The audit as a page under Tools. The same numbers the command prints,
    read-only: nothing here changes content, blueprints, or config.

    Statamic compiles a utility's HTML as a Vue template, so Statamic's
    `ui-*` components work here and a literal double brace would be read
    by Vue. Everything printed is a handle, a path, or a number, none of
    which can hold a brace.
--}}

<ui-header title="Fallow" icon="nature-leaf" />

<div class="space-y-6">

    <ui-description text="Which blueprint fields the content fills, which are dead weight, and which entry keys no blueprint field claims. Every entry localization counts once; a field inside a grid or a set is counted per row or per set instance." />

    @if (count($collections) > 1)
        <div class="flex flex-wrap items-center gap-2">
            <ui-button size="sm" href="{{ cp_route('utilities.index').'/fallow' }}" text="All collections" @if ($collection === null) variant="primary" @endif />
            @foreach ($collections as $handle)
                <ui-button size="sm" href="{{ cp_route('utilities.index').'/fallow?collection='.$handle }}" text="{{ $handle }}" @if ($collection === $handle) variant="primary" @endif />
            @endforeach
        </div>
    @endif

    @if ($unknown !== null)
        <ui-card-panel heading="Unknown collection">
            <p>There is no collection called <code>{{ $unknown }}</code>. Pick one above.</p>
        </ui-card-panel>
    @elseif ($tooMany !== null)
        <ui-card-panel heading="Too many entries to analyze here">
            <div class="space-y-2">
                <p>This scope has {{ number_format($tooMany) }} entry localizations and the control panel analyzes at most {{ number_format($limit) }} while a page loads (<code>cp_entry_limit</code> in <code>config/fallow.php</code>). Run the command instead:</p>
                <pre class="text-sm">php please fallow:audit{{ $collection === null ? '' : ' --collection='.$collection }}</pre>
                <p>Or narrow the page to one collection above.</p>
            </div>
        </ui-card-panel>
    @else
        @php($summary = $report['summary'])

        <ui-card-panel heading="Summary">
            <div class="flex flex-wrap gap-2">
                <ui-badge color="emerald" text="{{ $summary['healthy'] }} healthy" pill />
                <ui-badge color="amber" text="{{ $summary['sparse'] }} sparse" pill />
                <ui-badge color="red" text="{{ $summary['dead'] }} dead" pill />
                <ui-badge text="{{ $summary['never_instantiated'] }} never instantiated" pill />
                <ui-badge color="{{ $summary['orphan_keys'] > 0 ? 'amber' : 'default' }}" text="{{ $summary['orphan_keys'] }} orphan {{ $summary['orphan_keys'] === 1 ? 'key' : 'keys' }}" pill />
            </div>
            <ui-description text="{{ $summary['fields_total'] }} fields across {{ count($report['collections']) }} {{ count($report['collections']) === 1 ? 'collection' : 'collections' }}. A field is sparse below a fill rate of {{ $report['scope']['min_fill'] }}, dead at zero, and never instantiated when nothing could have held it." />
        </ui-card-panel>

        @foreach ($report['collections'] as $c)
            @foreach ($c['blueprints'] as $b)
                <ui-card-panel heading="{{ $c['handle'] }} / {{ $b['handle'] }}">
                    <div class="space-y-4">
                        <ui-description text="{{ $b['entries_analyzed'] }} entry {{ $b['entries_analyzed'] === 1 ? 'localization' : 'localizations' }} analyzed." />

                        <ui-table>
                            <ui-table-columns>
                                <ui-table-column>Path</ui-table-column>
                                <ui-table-column>Type</ui-table-column>
                                <ui-table-column>Filled</ui-table-column>
                                <ui-table-column>Denominator</ui-table-column>
                                <ui-table-column>Rate</ui-table-column>
                                <ui-table-column>Status</ui-table-column>
                            </ui-table-columns>
                            <ui-table-rows>
                                @foreach ($b['fields'] as $f)
                                    <ui-table-row>
                                        <ui-table-cell><code>{{ $f['path'] }}</code></ui-table-cell>
                                        <ui-table-cell>{{ $f['type'] }}</ui-table-cell>
                                        <ui-table-cell>{{ $f['filled'] }}</ui-table-cell>
                                        <ui-table-cell>{{ $f['denominator'] }}</ui-table-cell>
                                        <ui-table-cell>{{ $f['fill_rate'] === null ? '-' : number_format($f['fill_rate'], 4) }}</ui-table-cell>
                                        <ui-table-cell>
                                            <ui-badge text="{{ str_replace('_', ' ', $f['status']) }}" @if ($f['status'] === 'healthy') color="emerald" @elseif ($f['status'] === 'sparse') color="amber" @elseif ($f['status'] === 'dead') color="red" @endif />
                                            @if ($f['always_false'] ?? false)
                                                <ui-badge text="always false" />
                                            @endif
                                        </ui-table-cell>
                                    </ui-table-row>
                                @endforeach
                            </ui-table-rows>
                        </ui-table>

                        @if ($b['orphan_keys'] === [])
                            <ui-description text="No orphan keys." />
                        @else
                            <div>
                                <div class="font-medium">Orphan keys</div>
                                <ui-description text="Keys in an entry's own data with no top-level field in this blueprint." />
                                <ul class="mt-2 space-y-1 text-sm">
                                    @foreach ($b['orphan_keys'] as $o)
                                        <li><code>{{ $o['key'] }}</code> in {{ $o['entries'] }} {{ $o['entries'] === 1 ? 'entry' : 'entries' }}, for example {{ implode(', ', $o['samples']) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </ui-card-panel>
            @endforeach
        @endforeach
    @endif

</div>
