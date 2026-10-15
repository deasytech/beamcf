# Phase 5: Product Detail Page

## Goal

Recreate the Poco-inspired product page with a product gallery, detailed product information, options, purchase controls, tabs, and related products.

## Prerequisite

Shared layout and product card components are complete. The quick-view implementation should be complete if it shares gallery or option components.

## 1. Breadcrumbs

Display the correct breadcrumb trail using OpenCart's existing category and product routing behavior.

## 2. Main product area

Use a responsive two-column desktop layout.

### Gallery

- Main product image.
- Thumbnail images.
- Accessible image navigation.
- Zoom or lightbox if practical and compatible with the existing libraries.
- Video only when real product video data exists.

### Product information

Display available fields:

- Product name.
- Rating and review count.
- Model and product code.
- Manufacturer.
- Stock availability.
- Reward points where enabled.
- Price.
- Special price and discount when applicable.
- Tax or other pricing information where appropriate.
- Product options.
- Quantity selector.
- Add to Cart.
- Buy Now only if it can safely use the real checkout flow.
- Wishlist.
- Compare.
- Size chart or product information links only when configured.
- Shipping, returns, and support information only when accurate.

Use OpenCart's own price formatting, tax configuration, currency, and stock data.

## 3. Options and validation

Support the option types used by the store, including select, radio, checkbox, text, textarea, file, date, time, and other supported native types.

- Mark required options.
- Display validation messages.
- Include options in cart requests.
- Reflect actual option-dependent price changes.
- Handle out-of-stock or unavailable variants correctly.
- Never assume all products have the same options.

## 4. Purchase interactions

- Add to Cart must use OpenCart's actual cart flow.
- Quantity must be validated.
- Wishlist and compare must use existing behavior.
- Buy Now, if included, must take the customer through a valid checkout route without bypassing required validation.
- Display server errors and success notifications clearly.

## 5. Product information sections

Create tabs or accessible sections for:

- Description.
- Specifications or attributes.
- Reviews.
- Additional information or custom tabs when an installed extension supports them.

Load the content from the real product record and existing extension data. Do not invent product specifications or reviews.

## 6. Related products

Display real related products or a supported recommendation module. Reuse the shared product card component. Keep the section responsive and consistent.

## 7. Responsive and accessible behavior

- Stack gallery and product information on small screens.
- Keep the purchase controls easy to reach.
- Prevent image overflow.
- Ensure tabs and gallery controls work with keyboard and touch.
- Use correct form labels and visible focus.
- Optimize image loading and avoid layout shifts.

## Acceptance criteria

- Product details match the selected catalog record.
- Gallery images work.
- Options and validation work.
- Price and stock reflect OpenCart data.
- Cart, wishlist, and compare actions work where enabled.
- Tabs display actual data.
- Related products use valid records.
- Desktop and mobile layouts are visually consistent.
- Report modified files and tests performed.
