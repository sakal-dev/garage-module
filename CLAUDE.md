# Garage Module — CLAUDE.md

Guidance for Claude Code when working on the Garage module. See the root `CLAUDE.md`
for project-wide rules and `docs/modular_rules.md` for the module rulebook.

This file exists to carry the contract block below; module-specific guidance is added
as it is written.

---

## Module boundary contract (Socheat, 2026-08-18 — enforced, not advisory)

Four rules. The ratchet `tests/Architecture/ModuleBoundaryTest.php` (LARA-244) fails
the build naming the file on any violation — **if your change fails it, the change is
wrong, not the test.**

1. **Core never imports modules.** Nothing under `app/` references `Modules\`. Core
   exposes registries (MenuService, ChecklistRegistry, FeatureRegistry, permission
   seeding); modules plug in from THEIR ServiceProviders.
2. **Dependencies are one-way and DECLARED.** A module may import `Modules\X\` only if
   X is in its `module.json` `requires`; the graph is acyclic. POS depends on
   Customer / Product / Order / Payment / Inventory / Billing — none of those may
   depend on POS. A module POS *needs* must work on an install that has no POS.
3. **Cross-module keys are OWNER-REGISTERED.** Feature keys, permissions, menu items,
   checklist items are registered by the module that owns the code, from its own
   provider, prefixed by that module or bare — never by the product that sells them
   (`customer_db`, not `pos.customer_db`). Other modules reference them only through
   the registry, never as a hard-coded string.
4. **An absent module leaves no dangling gate.** Nothing may gate on a key, permission,
   or route of a module that is not installed — independence is structural.

Every brief touching more than one module carries this block. Full rulebook:
`docs/modular_rules.md`.
