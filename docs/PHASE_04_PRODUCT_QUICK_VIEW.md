# Phase 4: Product Quick-View Modal

## Goal

Implement a responsive product quick-view modal similar to the supplied third screenshot, using real product data and OpenCart 4.x cart behavior.

## Prerequisite

The shared layout and product card component are complete.

## 1. Modal presentation

On desktop, show a centered modal with a dimmed overlay and two columns.

### Left column

- Main product image.
- Thumbnail gallery.
- Previous/next image controls where useful.
- Wishlist control.
- Video only if the product has valid video content and the project supports it.

### Right column

- Product name.
- Rating and review count when available.
- Manufacturer.
- Model or product code.
- Reward points if enabled.
- Availability or preorder status.
- Current price.
- Previous price and special discount when applicable.
- Required product options.
- Quantity selector.
- Appropriate purchase action.

Use a clear close button. On mobile, use a viewport-fitting panel or full-screen modal.

## 2. Product loading

The modal must load the product selected by the user. Do not reuse static sample information or accidentally show the same product for every card.

Inspect the installed OpenCart version and determine the correct controller, route, token, model, and response conventions. If a custom endpoint is needed, implement it using OpenCart's conventions and only return the data needed by the modal.

Validate that the requested product exists and is accessible. Escape rendered output and handle unavailable products gracefully.

## 3. Product options

Support the option types actually used by the store, including select, radio, checkbox, text, textarea, file, date, time, and other native types when applicable.

- Mark required options.
- Display validation messages.
- Include selected options in the cart request.
- Update price only according to the actual option pricing logic.
- Do not assume all products have size or color options.

## 4. Cart integration

- Use the actual OpenCart add-to-cart route and expected request parameters.
- Include product identifier, quantity, and selected options correctly.
- When the product has subscription plans, show a plan selector and submit the selected `subscription_plan_id`. OpenCart rejects a subscription product added without a valid plan, so display that error clearly.
- Preserve required security tokens and session behavior.
- Display success and error feedback.
- Update the cart indicator and mini-cart using the existing project mechanism.
- Prevent double submissions while a request is pending.
- Do not duplicate cart calculations in JavaScript.

## 5. Accessibility and interaction

- Open from the correct product card.
- Keep focus within the dialog while open.
- Close on Escape.
- Return focus to the trigger when closed.
- Prevent background scrolling while open.
- Allow keyboard and touch operation.
- Use correct dialog semantics and accessible labels.
- Handle loading and request failure states.

## Acceptance criteria

- Each product opens its own correct details.
- Product images and information remain synchronized.
- Required options are validated.
- Adding to cart persists through OpenCart.
- Errors and success states are visible.
- The modal is responsive and keyboard accessible.
- No new console or server errors are introduced.
- Report endpoint or route changes, files modified, and tests performed.
