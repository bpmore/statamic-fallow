# Fallow: Build Brief for Claude Code

Read this file first, then `fallow-spec.md`.

`fallow-spec.md` is the normative specification: definitions, JSON schema, command and
utility behavior, performance budget, and the fixtures with their expected results. This
brief covers everything around it: where to work, what order to build in, what not to do,
and how to hand off. Where the two conflict, this brief wins.

---

## 1. Working agreement

The repo is at `~/Herd/statamic-fallow`. Package `bpmore/statamic-fallow`, namespace
`Bpmore\Fallow`. Look at what is already there before assuming it is empty: if it is
scaffolded, work with what exists rather than starting over.

Develop it against a local Statamic site by adding the package as a Composer path
repository in that site's `composer.json`, so edits take effect without a reinstall:

```json
"repositories": [
    { "type": "path", "url": "../statamic-fallow" }
]
```

Then `composer require bpmore/statamic-fallow:@dev` from the site. Confirm the symlink
landed rather than a copy, since a copy means your edits do nothing and you will lose an
hour to that.

Match the structure and conventions of the existing `bpmore/statamic-a11y-report` repo:
read it before you start and follow what it does for composer metadata, service provider
layout, config publishing, and README shape. If it disagrees with this brief on a
convention, follow the existing repo and say so.

Small commits, one logical unit each. Conventional commit prefixes.

**Stop and ask** when any of these happen. Do not guess and keep going:

- The installed Statamic version contradicts a definition in section 4. That is real
  information, not an obstacle to route around.
- A fixture in section 9 cannot be constructed as written, for example if the entry
  factory will not let you set an origin chain two levels deep.
- An expected number in section 9.2 or 9.5 appears to be wrong. It may well be. Say
  which number, show your derivation, and wait.

Proceed without asking on everything else, including naming, file organization, and any
choice the spec does not constrain.

## 2. Establish the API surface yourself

Section 3 of the spec names two load-bearing claims and tells you to confirm them from
source rather than trust them. Do that first.

Verify against `vendor/statamic/cms` before writing analyzer code:

- How `Statamic\Entries\Entry` implements `data()`, `values()`, `value()`, and `origin()`.
  Read `Statamic\Data\HasOrigin` in particular: the resolution rule in spec section 4.6
  is a claim about this trait's behavior, and you should confirm or refute it.
- Whether the entry query builder supports chunked iteration, and under what method name.
- The shape `Statamic\Fields\Field::config()` returns for `replicator`, `bard`, `grid`,
  and `group`, including how set groups nest.

Write a short `NOTES.md` recording what you found, with file and line references. If any
of it contradicts the spec, that goes in section 1's stop-and-ask list.

## 3. Build order

**Tests first, and the tests are the contract.**

1. Scaffold the package: composer.json, service provider, config, CI-less Pest setup.
2. Translate spec sections 9.1, 9.2, 9.4, and 9.5 into Pest tests. Fixtures as real YAML
   under `tests/__fixtures__`. Every expected number from those tables becomes an
   assertion. Commit this before writing any analyzer code. The tests will be red. That
   is correct.
3. Build `FieldPath`, `BlueprintWalker`, `ValueInspector`, `Auditor`, `Report`, in that
   order, each with its own unit tests beyond the section 9 cases.
4. Command, then utility.
5. Green run, then the manual verification in section 5 below.

Class contracts from spec section 10 still apply: `ValueInspector::isFilled()` pure and
Statamic-free, `BlueprintWalker` entry-agnostic, `Auditor` the only class touching
repositories.

## 4. Guardrails

These exist because the failure modes are predictable, not because I expect bad faith.

- **Never edit a test from section 9 to make it pass.** Those numbers are the
  specification. If one looks wrong, stop and ask per section 1. Changing an assertion to
  match your output is the single worst outcome of this task and silently destroys the
  point of the exercise.
- **Never special-case a fixture.** No branch keyed on entry ID, site handle, collection
  handle, or field handle from section 9. The logic must be general. A reviewer will read
  `ValueInspector` and `Auditor` before reading your test output.
- **Do not skip, mark incomplete, or comment out a failing test.** A red test at handoff
  with an explanation is a better result than a green suite that does not test the thing.
- **Do not widen scope.** Section 2 of the spec lists non-goals. Adding fieldset support
  or a Vue page counts against the work, not for it.
- Do not add dependencies beyond `statamic/cms` and dev tooling without asking.

## 5. Definition of done

1. `vendor/bin/pest` green, with every section 9 assertion present and passing.
2. `php please fallow:audit --json` on the fixture site emits parseable JSON with nothing
   else on stdout.
3. Manual run against the greenhouse testbed, which has real multisite content and real
   replicators. Report what it found: dead fields, orphan keys, anything that looks wrong.
   If the output is obviously nonsense on real content while the fixtures pass, the
   fixtures are not covering something and that is worth more than a green suite.
4. `--fail-on=dead` returns exit 1 on the fixture site, 0 without the flag.
5. README written, config documented, permission documented.

## 6. House conventions

- No em dashes anywhere: code comments, README, CP copy, commit messages. Use a colon for
  introductions, a comma or parentheses for asides, or split into two sentences.
- This is Had A Farm work, so APA style does not apply. Plain technical prose.
- Markdown for any document you produce.

## 7. Handoff

Close with:

- **Findings**: anything in the spec you had to stop on, and what the source actually said.
- **Assumptions**: choices made where the spec was silent.
- **Known gaps**: what is unfinished, unsure, or untested. An honest gap list is worth
  more than a confident claim of completeness, and I will check.
