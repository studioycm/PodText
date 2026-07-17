# Step 5B Restricted Preview Selector Closure Research

## Control

- Laravel Simplifier audit: `LS-20260717-STEP5B-CLOSURE-01`.
- Approved option: `STEP5B-CLOSURE-O1`.
- Stage 2 baseline: clean `main` at
  `2861c320bbeb1091e57b436623241feea039f64a`.
- Scope: prevent a restricted Card Template preview shell from exposing or
  reaching its transient public-sample selector. This is contract enforcement
  and query suppression; it does not remediate a protected-data disclosure.

## Verified behavior

1. `CardTemplateEditorPage::refreshPreview()` establishes `previewFamily`
   before returning the `restricted` state. `clearPreview()` clears the
   rendered result and sample scalar values but intentionally retains the
   family for the restricted shell.
2. `card-template-preview.blade.php` currently renders
   `choosePreviewSampleAction` for every valid family, including a restricted
   shell.
3. The action's `Select` search and selected-label callbacks directly call
   `CardTemplatePreviewer::sampleOptions()` and `sampleLabel()`. These methods
   use the existing deterministic public-safe item, group, and contributor
   query scopes and cap list results at 50.
4. `CardTemplateAccessPolicy` and `enforceCurrentCapability()` already own the
   current protected-template boundary. A non-capable editor remains allowed
   to preview an ordinary non-protected template; the closure must not change
   that behavior or introduce a new permission.
5. Installed Filament 5.6.7 source shows that `mountAction()` rejects a
   disabled action before building its schema. Visibility alone is therefore
   insufficient for forged action requests. Installed `Select` source shows
   that both search and option-label resolution evaluate the configured
   callbacks.
6. Existing restricted coverage proves mount-time absence of item/author
   queries and protected output, but does not prove action visibility,
   action-mount rejection, or all three sample-query paths. The browser and
   SP3C canary tests cover normal preview behavior, not this restricted action
   interaction.

## Installed-version research

Laravel Boost reported Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, and
Pest 4.7.4. Its version-aware documentation confirms that custom actions need
end-to-end authorization tests and that custom searchable-select results and
selected labels are independently resolved callbacks.

FilamentExamples provided two search/snippet passes only, not a source/detail
endpoint. The useful patterns were ordinary custom-page actions and searchable
selection. PodText will retain its existing action and `Select`; no example
justifies a new permission layer, duplicate state, or a generic preview
service.

## Focused conclusion

The smallest safe boundary is a private page predicate which is false when the
shell is restricted, when a protected template has lost its existing current
capability, when the preview status is restricted, or when no valid family is
available. It will be used for action visibility, action disablement, and each
callback/action entry point before the previewer is resolved. The Blade view
will additionally not render the action for the restricted status.

`CardTemplatePreviewer` remains unchanged: its public-safe sample semantics
are required by authorized previews and other existing tests. No persistence,
settings, lifecycle, model, migration, dependency, or capability architecture
change is needed.

## Verification boundary

Focused component tests will prove a restricted shell hides and disables the
action, rejects a forged action mount, and performs no post-mount
`content_items`, `content_groups`, or `authors` sample lookup. They will retain
the protected-sentinel HTML/state assertion. Authorized component tests will
exercise the actual mounted selector's search and label callbacks, submission
and refresh for item, group, and contributor families, and the existing
50-result bound. Existing browser and SP3C canary assertions will be run
unchanged to prove their boundaries remain intact.
