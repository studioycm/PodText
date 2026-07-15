---
name: filament-forms-ux-audit
description: "Audit or improve Filament 5 form UX in resources, pages, relation managers, action modals, settings pages, and schema components. Use when building, editing, diagnosing, or reviewing form() or schema() definitions, TextInput, Select, DatePicker, Toggle, Textarea, RichEditor, Repeater, Builder, Section, Grid, Tabs, conditional fields, validation guidance, relationship lookup UX, localization, RTL behavior, or create-versus-edit flows."
---

# Filament Forms UX Audit

Review Filament form schemas for material usability, state, validation, and
consistency problems. Recommend focused changes that match the installed
version and the application's existing conventions.

## Required context

1. Read repository agent/project guidance and nearby forms before auditing.
2. Confirm installed Filament and Livewire versions with Laravel Boost
   `application_info` when available.
3. Use Laravel Boost `search_docs` before recommending version-sensitive APIs.
4. Use configured Filament example research when changing Filament code and
   report whether access was search/snippet or full source.
5. Inspect the model casts, migrations, request/form validation, policies, and
   tests when a finding depends on storage, authorization, or nullable intent.
6. Activate the repository's Laravel/PHP conventions when implementing fixes.

## Goals

- Clarity of input expectations
- Speed of data entry
- Prevention of invalid input
- Consistency across forms
- Logical grouping of related fields
- Reduced overwhelm on complex forms

## Principles

1. **Clarity over minimalism** — a helper text or placeholder that prevents one support ticket is worth the extra line of code
2. **Reduce user thinking** — the form should communicate expected format, constraints, and meaning without the user needing to guess
3. **Prevent mistakes early** — use the right input type, validation, and constraints so errors are impossible rather than caught after submission
4. **Group related fields** — fields that belong together conceptually should be visually grouped with Sections and Grids
5. **Use the right input type** — a DatePicker for dates, Select for constrained choices, Textarea for multi-line content
6. **Keep formatting consistent** — same date formats, currency prefixes, toggle colors, and casing rules across all forms

## Guardrails

- Prefer existing app conventions over introducing a new pattern
- Only flag an issue when it creates friction, ambiguity, data-quality risk, or inconsistency within the same domain
- Do not recommend placeholders, helper text, tabs, autofocus, toggle colors, or `->native(false)` as defaults without explaining why they help in that specific form
- Placeholders support labels, they do not replace labels or helper text
- Use Filament v5 APIs and examples
- Do not flag `->unique()` as needing `ignoreRecord: true`. In Filament v4+, `->unique()` automatically ignores the current record when the form is bound to an Eloquent model (e.g., in panel resources). This is not a bug.
- Do not flag password fields hidden on edit as a UX issue. Admin-editable passwords are a security anti-pattern. Password resets should go through dedicated flows (forgot password, 2FA) rather than admin forms.
- Do not assume Sections, Tabs, collapsed containers, or Builder previews reduce
  PHP schema construction or Livewire payloads. Treat that as a performance
  claim requiring measurement.
- For growing relationship data, prefer constrained server-side search with a
  capped result set. Preload only bounded sets and verify selected-option label
  resolution and validation.
- Review hidden conditional state deliberately. Decide whether hidden values
  should be preserved, cleared, or excluded from dehydration; never recommend
  dropping state without checking the storage contract.
- Treat localization, RTL/LTR direction, logical action placement, locale date
  formats, and accessible labels as part of UX when the application supports
  them.
- Keep Livewire 4 state on the server. Use per-keystroke `live()` only when the
  interaction needs it; prefer blur or change boundaries for text fields.
- Audit-only requests do not authorize code changes.
- If there are no material UX issues, say so explicitly

## Severity Levels

- **High** — likely to cause invalid data, failed workflows, hidden state problems, or major cognitive overload
- **Medium** — creates meaningful friction, ambiguity, or avoidable data inconsistency
- **Low** — polish or situational refinement

## Review Workflow

1. Start with the overall flow: scan order, sectioning, density, and create vs edit behavior
2. Check whether each field uses the right component, validation, and affordances for the data
3. Check cross-field behavior: ranges, dependencies, conditional visibility, and hidden state
4. Check whether the form follows existing app patterns before suggesting new conventions
5. Check localization, RTL, keyboard/responsive behavior, action placement, and
   whether important errors are discoverable in inactive containers.
6. Distinguish findings verified in code from findings that require browser or
   production-data evidence.
7. Only include low-severity polish items after higher-impact issues are covered.

## Output Format

Number each finding sequentially starting from 1. This allows the user to reference findings by number when requesting fixes (e.g., "fix #2, #5, and #9").

When reviewing a form, output findings as:

### #N. [Severity] Issue Title

**Where:** Identify the field, section, tab, or interaction.

**Problem:** Explain what the UX issue is and why it matters.

**Impact:** Explain how it affects admin speed, clarity, or data quality.

**Recommendation:** Explain the fix with reasoning.

**Confidence:** High / Medium / Low

**Evidence:** Verified / Inferred

**Filament example (only if useful):**
```php
// code using Filament v5 namespaces
```

End the audit with a numbered summary table so the user can quickly scan and
pick fixes. For a clean audit, state the residual browser/data-volume risks.

## Review Priorities

1. **Structure** — flat forms, missing sections, overwhelming field counts
2. **Input types** — wrong component for the data type
3. **Validation** — missing constraints that allow bad data
4. **Cross-field behavior** — ranges, dependencies, and conditional field state
5. **Guidance** — missing placeholders, helper text, format hints when they would materially reduce confusion
6. **Consistency** — different patterns for similar fields across forms

## Quick Reference

### Structure Rules
- [Flat Form Structure](rules/structure-flat-form.md) — forms with 8+ fields need Sections
- [Logical Grouping](rules/structure-logical-grouping.md) — group fields by domain meaning
- [Section Height Balance](rules/structure-section-height-balance.md) — balance visual height when sections sit side-by-side
- [Paired Fields](rules/structure-paired-fields.md) — related fields side-by-side in Grid
- [Full-Width Textareas](rules/structure-full-width-textareas.md) — textareas need columnSpanFull()
- [Tabs vs Sections](rules/structure-tabs-vs-sections.md) — use tabs only when they reduce overload without hiding critical fields

### Input Rules
- [Wrong Input Type](rules/inputs-wrong-input-type.md) — use Select/DatePicker for structured data
- [Textarea vs TextInput](rules/inputs-textarea-vs-textinput.md) — multi-line content needs Textarea
- [Missing Placeholders](rules/inputs-missing-placeholders.md) — use examples only when format is not obvious
- [Helper Text](rules/inputs-helper-text.md) — clarify ambiguous or business-specific fields
- [Nullable Intent](rules/inputs-nullable-intent.md) — explain what empty means
- [Unit Suffixes](rules/inputs-unit-suffixes.md) — clarify numeric units
- [Max Length Hints](rules/inputs-max-length-hints.md) — constrain short-string fields
- [Slug Auto-Generation](rules/inputs-slug-auto-generation.md) — auto-generate slugs from titles
- [Autofocus First Field](rules/inputs-autofocus-first-field.md) — only for high-frequency create flows where it helps
- [Relationship Select UX](rules/inputs-relationship-select-ux.md) — make relation fields findable and efficient

### Validation Rules
- [Date Constraints](rules/validation-date-constraints.md) — prevent invalid/past dates
- [URL Fields](rules/validation-url-fields.md) — validate and hint URL format
- [Cross-Field Ranges](rules/validation-cross-field-ranges.md) — enforce start/end and min/max relationships
- [Conditional Hidden Fields](rules/validation-conditional-hidden-fields.md) — avoid stale hidden values and unclear dependencies

### Consistency Rules
- [Currency Format](rules/consistency-currency-format.md) — follow the app's monetary convention consistently
- [Date Format](rules/consistency-date-format.md) — standardize date display while choosing native vs JS picker intentionally
- [Toggle Colors](rules/consistency-toggle-colors.md) — use color intentionally where scan speed matters
- [Uppercase Enforcement](rules/consistency-uppercase-enforcement.md) — enforce casing for codes
