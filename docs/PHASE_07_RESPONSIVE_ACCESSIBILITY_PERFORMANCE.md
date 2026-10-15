# Phase 7: Responsive Design, Accessibility, and Performance

## Goal

Refine the implemented storefront so it remains usable, accessible, and performant across desktop, tablet, and mobile devices.

## Prerequisite

The main storefront pages, product interactions, cart, and checkout have been implemented.

## 1. Viewport testing

Test approximately these viewport widths:

- 360px.
- 390px.
- 768px.
- 1024px.
- 1440px.

Also check intermediate widths where the layout transitions between breakpoints.

### Desktop

- Full navigation.
- Centered logo.
- Sidebar plus product grid.
- Four product columns where space allows.
- Two-column product details.
- Full footer columns.

### Tablet

- Reduced product columns.
- Adapted header and navigation.
- Collapsible catalog filters.
- Flexible promotional banners.
- Stacked product detail sections where appropriate.

### Mobile

- Compact announcement bar.
- Accessible mobile navigation.
- Search, account, wishlist, compare, and cart access.
- Two-column product grid where readable, otherwise one column.
- Filter drawer or accordion.
- Touch-friendly product controls.
- Product gallery within viewport width.
- Modal panel that fits the viewport.
- Checkout fields without horizontal scrolling.
- Stacked or collapsible footer sections.

## 2. Visual defect review

Look for and fix:

- Horizontal overflow.
- Overlapping navigation.
- Cropped or distorted images.
- Inconsistent product card heights.
- Product names colliding with prices.
- Badges covering important product details.
- Modals that exceed viewport dimensions.
- Sticky elements covering content.
- Form controls too small for touch.
- Excessive whitespace or unexpected page jumps.
- Broken layout when optional sections are absent.

## 3. Accessibility

Implement and test:

- Semantic landmarks and heading order.
- Accessible form labels and error messages.
- Visible keyboard focus.
- Keyboard-operable navigation and dropdowns.
- Accessible modal behavior and focus management.
- Meaningful image alternative text.
- Sufficient color contrast.
- Correct button names and link purposes.
- Reduced-motion support.
- Status messages announced appropriately where practical.

Do not use color alone to communicate stock, error, or sale status.

## 4. Performance

- Reuse existing libraries rather than loading duplicates.
- Remove unused theme-specific CSS and JavaScript where safe.
- Optimize image sizes and formats where possible.
- Lazy-load below-the-fold images.
- Provide width and height or aspect ratio to reduce layout shifts.
- Avoid blocking scripts where possible.
- Debounce search input when applicable.
- Avoid repeated requests for the same product data.
- Avoid unnecessary animations and large dependencies.
- Confirm that performance improvements do not break OpenCart or extensions.

## 5. Browser and error checks

Check the browser console, PHP logs, and OpenCart logs available in the development environment. Verify that the theme does not introduce new warnings, template exceptions, failed requests, or JavaScript errors.

Do not suppress errors simply to make the console appear clean. Identify and fix their causes.

## Acceptance criteria

- Layout works at all target widths.
- No horizontal overflow on key pages.
- Navigation, filters, product actions, modal, cart, and checkout remain usable.
- Keyboard focus is visible and interactions are accessible.
- Image loading and layout shifts are reasonable.
- No new unresolved errors are introduced.
- Report devices/viewports tested and any remaining issues.
