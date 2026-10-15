# Phase 1: Theme Foundation and Shared Layout

## Goal

Establish the custom theme foundation and implement the shared design system, announcement bar, header, navigation, and footer.

## Prerequisite

Phase 0 is complete and the user has approved the implementation plan.

## Design reference

The supplied Poco Mega Store screenshots use a clean white background, black navigation and footer, warm yellow-orange accents, compact promotional labels, strong typography, and restrained borders.

## Instructions for Claude

Follow the project findings from Phase 0. Implement this phase using the conventions of the exact OpenCart 4.x release. Do not copy OpenCart 3.x file paths or APIs without verifying compatibility.

### 1. Theme and asset strategy

- Use a maintainable theme or supported override strategy appropriate to this installation.
- Keep changes separate from unrelated application code.
- Do not edit core files, vendor files, or generated caches unless a documented blocker makes it unavoidable.
- Reuse the existing CSS/JS toolchain and libraries where suitable.
- Keep asset paths and template references valid for OpenCart 4.x.
- Document any theme registration or configuration step required.

### 2. Design tokens

Create centralized CSS custom properties for:

- Primary accent: `#FFB600`, or a visually equivalent warm yellow-orange.
- Black surfaces: approximately `#050505`.
- White background: `#FFFFFF`.
- Secondary background: `#F5F6F7`.
- Borders: `#E5E5E5`.
- Primary text: `#222222`.
- Secondary text: `#777777`.
- Error, warning, and success states with accessible contrast.
- Container width, spacing scale, typography scale, border radii, and overlay layers.

Use a clean sans-serif font. Reuse an existing suitable font or select a practical alternative such as Inter. Avoid excessive shadows, large rounded cards, gradients, and unrelated decorative effects.

### 3. Announcement bar

Build a compact top bar containing configurable or existing store information:

- Shipping or promotional message.
- Download app link, if configured.
- Currency selector.
- Language selector.
- Account menu.

Use actual OpenCart settings and routes. Do not hardcode fake store details.

### 4. Main header

Create a white header with:

- Shop by Category toggle on the left.
- Configured store logo centered on desktop.
- Search, wishlist, compare, and cart actions on the right.
- Cart item count badge.

Use existing routes and behavior. Product search must use the store's actual search mechanism.

### 5. Main navigation

Create a black navigation bar with warm yellow-orange active states. Include the available equivalents of:

- Home.
- Special offers.
- Products or categories.
- Mega Menu, if the category structure supports it.
- Blog, only if an existing blog feature is installed.
- Configured extra menu links.
- Contact Us.
- Search field where appropriate.

Use existing category and information data where possible. Menus must work with keyboard, mouse, and touch.

### 6. Shared footer

Build a reusable black footer containing:

- Actual store logo.
- Configured social links.
- Store address, telephone, and email when configured.
- Newsletter signup only if a real newsletter integration exists.
- My Account, Quick Links, and Information columns using valid routes.
- Configured manufacturer links or brand strip where data is available.
- Copyright text and appropriate payment icons.
- Back-to-top control.

Do not use example contact details from the reference screenshots.

### 7. Responsive baseline

Implement initial breakpoints for desktop, tablet, and mobile. The header and footer must not overflow or overlap at narrow widths. Mobile navigation should use an accessible drawer or dropdown.

### 8. Quality requirements

- Semantic HTML and correct heading hierarchy.
- Accessible labels and keyboard focus.
- Namespaced CSS classes to avoid extension conflicts.
- No duplicate JavaScript libraries.
- No console or template errors introduced by this phase.

## Acceptance criteria

- Shared header and footer render across the intended storefront routes.
- Logo, search, currency, language, account, cart, wishlist, and compare actions use valid existing functionality where enabled.
- Design tokens are centralized and reusable.
- Desktop styling resembles the reference.
- Header and footer work on mobile.
- No unnecessary core modifications.
- Report changed files, manual setup steps, and tests performed.
