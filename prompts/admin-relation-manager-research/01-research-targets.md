# Research Targets

## Tool usage

Use:

- Laravel Boost documentation search when available.
- Official Filament 5 docs through Boost `search-docs` when available.
- The configured `filament-examples` MCP server.

If Boost MCP is unavailable, say so and use installed docs / official docs / local inspection where possible.

Use FilamentExamples MCP accurately:

- If only `search_examples` is available, record that.
- If search results include source snippets, record them as source snippets returned by search.
- Do not claim a separate fetch/read/detail/source tool was used unless it exists.
- Do not write any MCP token or secret into any file.

## FilamentExamples MCP search subjects

Run targeted searches for examples/snippets around:

- relation managers;
- relation manager tabs;
- combined relation manager tabs with form content;
- content tab position;
- custom relation manager tab badge/icon;
- relation manager badge deferral;
- relation manager grouping;
- relation manager table actions;
- relation manager create/edit actions;
- relation manager custom query;
- relation pages;
- `ManageRelatedRecords`;
- resource sub-navigation;
- custom EditRecord page layout;
- custom CreateRecord redirect;
- custom EditRecord redirect;
- after create return to list;
- after edit return to list;
- create another disable;
- Livewire sidebar on edit form;
- custom admin edit pages;
- split Resource schemas/tables;
- sharing Resource form/table with relation manager;
- hiding form/table components on relation manager;
- rich HasMany management;
- nested repeaters vs relation managers;
- admin UX for long child records;
- Markdown editor in relation manager/modal;
- tabs above form;
- tabs with relationship management.

## Official docs subjects

Use Boost docs or official Filament docs for:

- Resource relation managers.
- Creating relation managers with `make:filament-relation-manager`.
- Registering relation managers in `getRelations()`.
- Combining relation manager tabs with form content.
- `hasCombinedRelationManagerTabsWithContent()`.
- `getContentTabComponent()`.
- `getContentTabPosition()`.
- Relation manager `getTabComponent()`.
- Deferred tab badges.
- Sharing a Resource form/table with a relation manager.
- `hiddenOn()` for shared form/table components.
- `modifyQueryUsing()` in relation managers.
- Relation pages / `ManageRelatedRecords`.
- Redirecting after CreateRecord save with `getRedirectUrl()`.
- Redirecting after EditRecord save with `getRedirectUrl()`.
- Disabling “create another” when relevant.
- Relation manager read-only behavior on View pages.
- Current Filament 5 file/namespace patterns.

## Evidence requirement

For every example or official-doc pattern, record:

- the source or tool used;
- whether you saw source snippets, summary only, or official docs;
- the exact pattern that applies to PodText;
- the risk or reason not to use the pattern.
