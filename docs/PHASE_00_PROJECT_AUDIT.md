# Phase 0: Audit the Existing OpenCart 4.x Project

## Goal

Understand the current installation and produce a safe, file-level implementation plan for a custom Poco-inspired storefront before changing code.

## Instructions for Claude

You are working inside an existing OpenCart **4.x** project that already has its default storefront, catalog data, and configuration. The target design is based on the supplied Poco Mega Store screenshots: homepage, product listing, quick-view modal, product detail, cart, and checkout.

**Do not make implementation changes during this phase.** Inspect first and report findings.

### 1. Inspect the actual project

Determine and document:

- Exact OpenCart 4.x version and PHP version.
- Project directory structure and document root.
- Active storefront theme and its directory.
- Twig template locations and version-specific conventions.
- Existing controllers, models, language files, assets, and theme configuration.
- CSS framework, JavaScript libraries, icon library, build tools, and asset compilation process, if present.
- Installed extensions and modifications that affect storefront, product pages, filters, quick view, cart, checkout, payment, shipping, blog, newsletter, testimonials, wishlist, or compare.
- Existing custom code or theme overrides that must be preserved.
- How catalog data and product images are passed into Twig templates.
- Current route conventions, URL generation, CSRF/token requirements, and session behavior for the installed release.
- Current cart and checkout architecture, including any extension that replaces the default checkout.
- Existing errors or unfinished work that could affect implementation.

Do not infer paths from OpenCart 3.x tutorials. Confirm them in this project.

### 2. Establish a baseline

Identify how to run the site locally or in its current development environment. Determine how to inspect PHP, Twig, JavaScript, and browser-console errors. Do not change production settings or perform destructive actions.

If possible, capture baseline screenshots of the existing homepage, category page, product detail, cart, and checkout.

### 3. Map reference screenshots to implementation areas

Create a table mapping each requested page or shared component to the current file, controller/model data source, and recommended customization method.

Cover:

- Shared announcement bar, header, navigation, search, footer.
- Homepage modules and banners.
- Category listing, filters, sorting, and pagination.
- Product cards and quick view.
- Product detail and options.
- Cart.
- Checkout.
- Responsive behavior.

### 4. Propose a safe implementation approach

Recommend the least invasive approach compatible with the exact OpenCart 4.x release. Prefer a dedicated theme or supported overrides where practical. If theme registration, extension packaging, or configuration changes are needed, explain them before making them in a later phase.

Do not introduce a new frontend framework or build system without a concrete reason.

### 5. Deliverables

Create or report:

1. Exact OpenCart and PHP versions.
2. Active theme and relevant directory paths.
3. Existing extensions that affect this work.
4. A file-level plan for each subsequent phase.
5. Compatibility risks and missing dependencies.
6. Recommended test procedure.
7. A clear list of files that must not be changed.

## Acceptance criteria

- No files or database records have been modified.
- Findings are based on actual project inspection.
- The implementation plan fits this OpenCart 4.x codebase.
- Uncertainties and extension dependencies are clearly identified.

Stop after presenting the audit and plan. Wait for approval before beginning Phase 1.
