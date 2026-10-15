# OpenCart 4.x Custom Storefront Theme: Poco-Inspired Implementation Plan

This prompt pack guides Claude through implementing a custom storefront inspired by the supplied Poco Mega Store screenshots in an **existing OpenCart 4.x project**.

## How to use

1. Open your existing OpenCart project in VS Code.
2. Keep a backup or create a Git commit before changes.
3. Share the screenshots with Claude or ensure they are available in the project context.
4. Start with `PHASE_00_PROJECT_AUDIT.md`.
5. Have Claude complete and verify one phase at a time.
6. Do not start a later phase until the current phase's acceptance criteria pass.

## Files

- `PHASE_00_PROJECT_AUDIT.md`: inspect OpenCart 4.x and plan safely.
- `PHASE_01_FOUNDATION_AND_SHARED_LAYOUT.md`: theme strategy, design tokens, header, navigation, footer.
- `PHASE_02_HOMEPAGE.md`: homepage sections and real store data.
- `PHASE_03_CATALOG_AND_FILTERS.md`: category listings, product cards, sorting, filtering, pagination.
- `PHASE_04_PRODUCT_QUICK_VIEW.md`: quick-view modal and real cart integration.
- `PHASE_05_PRODUCT_DETAIL.md`: product detail page, gallery, options, reviews, related products.
- `PHASE_06_CART_AND_CHECKOUT.md`: cart and checkout presentation while preserving OpenCart logic.
- `PHASE_07_RESPONSIVE_ACCESSIBILITY_PERFORMANCE.md`: responsive behavior, accessibility, and performance.
- `PHASE_08_QA_AND_HANDOFF.md`: regression testing, visual comparison, and final documentation.

## Global rules

- This is an existing OpenCart 4.x installation. Do not reinstall OpenCart or reset its database.
- Inspect the actual project before editing. Do not assume file paths, controller names, theme conventions, or installed extensions.
- Follow the conventions of the exact OpenCart 4.x release in the project.
- Preserve existing store data, customizations, payment and shipping integrations, sessions, routes, and checkout behavior.
- Prefer supported theme overrides and extension mechanisms. Do not modify core files unless there is a documented, unavoidable reason.
- Do not hardcode product names, prices, stock, ratings, taxes, shipping, cart totals, customer data, or order data.
- Never claim a feature is working unless it has been tested.
- If a feature depends on an extension or missing data, document that dependency and implement only what can be supported safely.
- Make incremental changes and report files changed after each phase.
