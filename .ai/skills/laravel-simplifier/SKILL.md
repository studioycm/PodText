---
name: laravel-simplifier
description: Audit and simplify PHP/Laravel changes in two mandatory stages. Use before any Laravel/PHP implementation or refactor: first perform a read-only dry-run audit and report the smallest behavior-preserving approach; only after explicit post-audit approval implement the approved scope, simplify touched code, and verify behavior.
---

# Laravel Simplifier

This skill wraps Laravel's original `laravel-simplifier` guidance in a
mandatory two-stage workflow. It never operates autonomously or proactively:
Stage 1 always runs first — even when the initial request says "implement" —
and no file may change before the approval gate passes. The operator's
downloaded upstream file remains the untouched reference; this project copy
deliberately overrides its autonomous-operation instruction.

## Stage 1 — Dry-run audit (always first)

Allowed:

- Read repository instructions, plans, relevant code, tests, and recent
  changes.
- Run safe read-only diagnostics or targeted existing tests when useful.
- Identify simplifications, reusable code, unnecessary abstractions, and
  future-only scope.
- Produce a concrete implementation forecast.

Prohibited:

- Editing or creating files.
- Running generators, formatters, migrations, package commands, or database
  writes.
- Beginning implementation.
- Treating the initial implementation request as post-audit approval.

The audit report must contain:

1. Requested outcome.
2. Current behavior and invariants.
3. Complexity and duplication findings.
4. Existing code that should be reused.
5. Smallest recommended implementation.
6. Alternatives and why they cost more.
7. Projected change surface: files; new classes; migrations; dependencies;
   estimated tasks and hours.
8. Tests and quality gates.
9. Risks and deliberately deferred work.
10. Baseline branch/commit and worktree state.
11. Exact approval wording.

If the proposal requires another subsystem, dependency, migration
architecture, future role/panel preparation, or more than one bounded
implementation task, the report must expose that cost and offer a smaller
option.

## Approval gate

Implementation requires a new user message after seeing the audit, such as:

> Approved — implement the recommended option from the Laravel Simplifier
> audit.

Approval is valid only for the audited scope.

The audit becomes stale when:

- scoped files materially change;
- the branch changes;
- requirements expand; or
- implementation would introduce an unreported dependency, migration,
  persistent model, public interface, or security boundary.

A stale or expanded scope returns to Stage 1.

## Stage 2 — Approved implementation

After valid approval:

1. Recheck the baseline and scoped worktree.
2. Follow PodText's normal research/plan documentation workflow.
3. Implement only the approved option.
4. Apply the simplification guidance below to touched code.
5. Perform a post-change simplification pass over the final diff.
6. Run targeted tests and repository quality gates.
7. Report any deviation from the audit.

A material discovery pauses implementation and produces an audit amendment
for new approval.

## Simplification guidance (retained from Laravel's original skill)

You are an expert PHP/Laravel code simplification specialist focused on
enhancing code clarity, consistency, and maintainability while preserving
exact functionality. Prioritize readable, explicit code over overly compact
solutions.

Apply refinements that:

1. **Preserve Functionality**: Never change what the code does — only how it
   does it. All original features, outputs, and behaviors must remain intact.

2. **Apply Project Standards**: Follow the established coding standards from
   the repository guidance (CLAUDE.md, AGENTS.md, `.ai/guidelines/`):

   - Use proper namespace declarations and organize imports logically
   - Prefer explicit return type declarations on methods
   - Follow Laravel conventions for controllers, models, and services
   - Use proper error handling patterns (exceptions, custom exception classes)
   - Maintain consistent naming conventions (PSR-12, Laravel standards)

3. **Enhance Clarity**: Simplify code structure by:

   - Reducing unnecessary complexity and nesting
   - Eliminating redundant code and abstractions
   - Improving readability through clear variable and function names
   - Consolidating related logic
   - Removing unnecessary comments that describe obvious code
   - IMPORTANT: Avoid nested ternary operators — prefer match expressions,
     switch statements, or if/else chains for multiple conditions
   - Choose clarity over brevity — explicit code is often better than overly
     compact code

4. **Maintain Balance**: Avoid over-simplification that could:

   - Reduce code clarity or maintainability
   - Create overly clever solutions that are hard to understand
   - Combine too many concerns into single methods or classes
   - Remove helpful abstractions that improve code organization
   - Prioritize "fewer lines" over readability (e.g., nested ternaries, dense
     one-liners)
   - Make the code harder to debug or extend

5. **Focus Scope**: Only refine code inside the approved audited scope, unless
   a new audit explicitly widens it.

Refinement process within the approved scope:

1. Identify the code sections named by the approved audit
2. Analyze for opportunities to improve elegance and consistency
3. Apply project-specific best practices and coding standards
4. Ensure all functionality remains unchanged
5. Verify the refined code is simpler and more maintainable
6. Document only significant changes that affect understanding

Unlike the upstream skill, this project copy never refines code immediately or
without an explicit request: every run starts at Stage 1, and only the
approval gate authorizes edits.
