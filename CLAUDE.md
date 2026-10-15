# Claude Instructions: OpenCart 4.x Poco-Inspired Storefront

## Project goal

This repository contains an existing OpenCart 4.x installation. The storefront currently uses its default theme and already has store configuration and catalog data.

The goal is to implement a responsive, maintainable storefront inspired by the Poco Mega Store reference screenshots supplied by the user. The screenshots cover the homepage, product listing, quick-view modal, product detail, shopping cart, and checkout.

The detailed phase prompts are in the project-root `docs/` directory. These documents are the implementation plan and must be read and followed.

## Theme isolation requirement: preserve the default theme

The existing default storefront theme must be retained as a working fallback and must not be overwritten.

- During Phase 0, identify the active default theme, its directory, and the safest supported way to add a separate custom theme in this exact OpenCart 4.x release.
- The implementation must live in a **separate custom theme directory or supported theme extension/override**, not by replacing the default theme's templates or assets in place.
- Keep the default theme's original files unchanged. Do not rename, delete, repurpose, or overwrite the default theme.
- Do not assume a particular theme directory path or that creating a folder alone makes it selectable. Verify OpenCart 4.x theme registration, configuration, template lookup, and asset conventions in this repository.
- If this installation or an extension makes a genuinely separate theme impractical, do not silently edit the default theme. Explain the constraint, propose the safest alternative, and obtain the user's approval first.
- Keep custom CSS, JavaScript, Twig templates, and theme-specific assets isolated as much as the installed OpenCart architecture permits.
- Document how to activate the custom theme and how to switch back to the original default theme.
- Confirm in the final handoff which original theme files, if any, were changed. The expected result is that the default theme remains intact and available as a fallback.

## Required first actions

Before changing any code:

1. Read `docs/README.md`.
2. Read `docs/PHASE_00_PROJECT_AUDIT.md` completely.
3. Inspect the actual repository and determine the exact OpenCart 4.x release, PHP version, active theme, template conventions, installed extensions, customizations, and checkout implementation.
4. Follow Phase 0 and produce its audit and file-level implementation plan.
5. **Do not modify application files, database records, configuration, or production state during Phase 0.** The audit phase is inspection and planning only.
6. Present the findings, risks, proposed files to create or modify, and any blockers. Wait for the user's approval before starting Phase 1.

Do not skip the audit because the project is known to use OpenCart 4.x. Different OpenCart 4.x releases and extensions may use different conventions.

## Phase order

After the user approves the audit and plan, work through the prompts in this order:

1. `docs/PHASE_01_FOUNDATION_AND_SHARED_LAYOUT.md`
2. `docs/PHASE_02_HOMEPAGE.md`
3. `docs/PHASE_03_CATALOG_AND_FILTERS.md`
4. `docs/PHASE_04_PRODUCT_QUICK_VIEW.md`
5. `docs/PHASE_05_PRODUCT_DETAIL.md`
6. `docs/PHASE_06_CART_AND_CHECKOUT.md`
7. `docs/PHASE_07_RESPONSIVE_ACCESSIBILITY_PERFORMANCE.md`
8. `docs/PHASE_08_QA_AND_HANDOFF.md`

Read each phase prompt in full immediately before beginning that phase. Complete its acceptance criteria and report results before moving to the next phase. Do not implement all phases in one unreviewed batch.

## Non-negotiable project safeguards

- This is an existing OpenCart 4.x project. Do not reinstall OpenCart, reset the database, replace the application, or create a separate ecommerce system.
- Preserve existing catalog data, products, categories, manufacturers, images, customers, orders, store settings, and unrelated customizations.
- Preserve the existing cart, checkout, tax, shipping, payment, session, routing, and order-placement behavior.
- Inspect the actual code before deciding which files, routes, variables, APIs, or template conventions to use.
- Do not assume OpenCart 3.x file paths or APIs apply. Verify compatibility with the installed OpenCart 4.x release.
- Prefer a separate custom theme using supported OpenCart 4.x theme mechanisms. Preserve the original default theme unchanged as a fallback. Avoid core edits, vendor edits, and generated-cache edits. If a core change or modification of the default theme seems unavoidable, explain why and obtain approval first.
- Do not disable existing extensions merely to make the design easier to implement.
- Do not add a new frontend framework, build system, or dependency without a clear technical need.
- Never hardcode product names, prices, discounts, stock, ratings, product options, cart totals, taxes, shipping fees, customer details, or order details.
- Use OpenCart's existing data and business logic as the source of truth.
- Do not invent routes, Twig variables, controller methods, APIs, extension features, payment methods, testimonials, blog posts, or product data.
- Keep credentials, API keys, tokens, and other secrets out of frontend assets and source control.
- Do not place real orders or make real payments during testing. Use a safe development or staging environment and test payment settings.
- Do not claim a feature works unless it has been tested. Clearly identify unsupported, untested, or blocked features.
- Do not overwrite user changes. Inspect Git status and existing modifications before editing. Preserve unrelated work.
- Make small, incremental changes and check the relevant PHP/Twig/JavaScript/CSS behavior after each meaningful change.

## Design direction

Use the supplied Poco Mega Store screenshots as visual references. The intended style includes:

- White backgrounds, black navigation and footer, and warm yellow-orange accents.
- Clean sans-serif typography and strong heading hierarchy.
- Consistent product image areas, compact product badges, and aligned product cards.
- A promotional homepage with hero banners, category tiles, product showcases, and optional content sections where supported.
- A product listing with a filter sidebar, sorting, comparison, and pagination.
- A responsive product quick-view modal and detailed product page.
- Clear cart totals and a checkout layout that retains OpenCart's real checkout behavior.
- Responsive desktop, tablet, and mobile layouts.

Treat the screenshots as layout and styling guidance, not as permission to reproduce inconsistent sample data or fake interactions.

## How to work

For each approved phase:

1. Read the complete phase prompt.
2. Check the relevant project files and existing implementation.
3. State briefly what you intend to change and identify any material risk.
4. Implement the smallest coherent set of changes.
5. Run available checks and test the affected page or flow.
6. Review browser-console, PHP, Twig, and network errors where the environment allows.
7. Verify that existing OpenCart behavior still works.
8. Report files created or modified, tests run and their results, manual configuration steps, and remaining limitations.
9. Stop at the phase boundary and wait for approval before starting the next phase.

If a required detail is unknown, inspect the project first. Ask the user only when a decision or permission is genuinely required. Never bypass a safeguard simply to keep moving.

## Phase 0 deliverable

Before implementation, return an audit containing:

- Exact OpenCart 4.x version and PHP version.
- Active theme and relevant directory paths.
- Template and asset conventions used by this repository.
- Extensions or modifications affecting the requested pages.
- Current cart and checkout implementation.
- Proposed customization strategy.
- A file-level plan for each phase.
- Risks, missing dependencies, and items needing user approval.
- Baseline errors or existing issues discovered.
- Confirmation that no files or database records were modified.

## Completion standard

The work is complete only when the applicable phase acceptance criteria have been tested and documented. At final handoff, summarize the implementation, files changed, configuration steps, tests and results, extension dependencies, remaining issues, and rollback guidance.

Start now with the Phase 0 audit only. Do not begin Phase 1 until the user approves the audit and implementation plan.
