# Phase 6: Shopping Cart and Checkout

## Goal

Adapt the existing OpenCart 4.x cart and checkout presentation to the Poco-inspired reference without replacing the underlying shopping, payment, shipping, tax, session, or order logic.

## Prerequisite

The shared layout and product purchase flows are working.

## Critical rule

**Do not rebuild a working checkout from scratch.** First inspect whether the installation uses the default OpenCart checkout or an extension-provided checkout. Preserve its controller flow, routes, tokens, session behavior, validation, payment integrations, and order creation.

## 1. Shopping cart layout

Recreate the reference with:

- Breadcrumbs and cart heading.
- Product image.
- Product name and selected options.
- Model or product code.
- Quantity control.
- Unit price.
- Line total.
- Update and remove actions.

Below the cart, add expandable panels for features supported by the installation:

- Coupon code.
- Shipping and tax estimation.
- Gift certificate or gift voucher.

Display an order summary with the actual applicable subtotal, shipping, taxes, discounts, charges, and final total.

Make the checkout button prominent using the primary yellow-orange accent. Provide a clear continue-shopping action.

## 2. Cart functionality

- Quantity updates must update the real OpenCart cart.
- Removing a product must persist.
- Selected product options must remain visible.
- Coupon validation must use existing logic.
- Shipping estimates must use configured methods and required address information.
- Tax calculations must remain server-side.
- Totals must never be hardcoded.
- Preserve the cart across navigation using the existing session mechanism.
- Display clear errors and success messages.
- Provide a useful empty-cart state.

Do not duplicate pricing or tax calculations in JavaScript.

## 3. Checkout layout

Match the reference's clear form layout while preserving the existing checkout implementation.

Support the available account choices:

- Login.
- Register.
- Guest checkout, if enabled.

Display applicable customer fields such as first name, last name, email, telephone, password, and password confirmation.

Support billing and delivery address fields appropriate to the configured country and store setup, including company, address lines, city, postcode, country, and region.

Preserve the option to use the billing address as the delivery address where supported.

## 4. Order summary and discounts

Display the actual products, quantities, unit prices, line totals, subtotal, shipping, taxes, discounts, and final total.

Preserve coupon, gift certificate, and order comment features when supported.

Update totals using the existing checkout workflow.

## 5. Shipping and payment methods

Render only methods that are available for the current customer, address, cart, and configuration.

Preserve all existing shipping and payment extensions. Do not create fake payment choices or a simulated payment-success flow.

## 6. Validation and order safety

- Preserve server-side validation.
- Preserve CSRF tokens, sessions, and required security checks.
- Display validation errors near the relevant fields where possible.
- Retain user-entered data after validation errors where the existing flow supports it.
- Handle unavailable shipping and payment methods.
- Prevent accidental duplicate submissions where practical.
- Respect terms, privacy, registration, and guest-checkout settings.
- Successful order placement must follow the original OpenCart order workflow.

Test order placement only in a safe development or staging environment with suitable test payment settings. Do not submit real orders or payments during development without explicit authorization.

## 7. Responsive behavior

On desktop, use a clear form and order-summary arrangement inspired by the reference. On mobile, stack sections vertically, make controls touch-friendly, and prevent horizontal scrolling.

## Acceptance criteria

- Cart quantities, removal, options, and totals work correctly.
- Coupons and shipping estimates use real OpenCart logic.
- Checkout preserves the configured account and address flow.
- Payment and shipping extensions remain operational.
- Order totals are calculated server-side.
- Test order flow is verified safely.
- No core checkout logic was unnecessarily replaced.
- Report changed files, tests, and any extension-specific limitations.
