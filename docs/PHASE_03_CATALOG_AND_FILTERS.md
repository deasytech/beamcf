# Phase 3: Product Listing, Category Pages, and Filters

## Goal

Recreate the supplied Poco-inspired catalog layout with a left filter sidebar, a clean product grid, and a compact toolbar while preserving OpenCart 4.x catalog behavior.

## Prerequisite

Phases 0 and 1 are complete. Phase 2 should be complete or the shared product card component must already be available.

## 1. Layout

On desktop:

- Breadcrumbs above the listing.
- Left sidebar approximately 220 to 260 pixels wide.
- Product grid using the remaining space.
- Four columns at suitable desktop widths, reducing columns when necessary.
- Consistent card dimensions and spacing.

On tablet, reduce columns and make filters collapsible. On mobile, use a filter drawer or accordion and a compact toolbar.

## 2. Catalog toolbar

Implement:

- Grid/list view toggle, if both views are supported.
- Products-per-page selector.
- Product comparison count and link.
- Sort selector.
- Product count.
- Pagination.

Use existing OpenCart 4.x route and query conventions. Preserve sorting, pagination, selected filters, and SEO behavior where supported.

## 3. Filter sidebar

Support the filters available in the current installation:

- Price range.
- Manufacturer.
- Product search.
- Color.
- Availability.
- Size.
- Discount percentage.
- Rating.

First inspect existing extensions, product attributes, options, and data structures. Do not create fake filters.

If a filter needs a missing extension or extra structured product data, explain the dependency. Implement only the filters that can operate correctly with the existing data or with an explicitly approved extension.

Filters must change the actual product results, not just their visual selection state. Preserve state in appropriate query parameters or supported request data and provide a clear reset action.

## 4. Product cards

Match the screenshot with:

- White image area.
- Consistent product image sizing.
- Product title.
- Rating stars and review count when available.
- Current price and previous price where applicable.
- Special price and discount where applicable.
- New, Sale, or Pre-Order badge only when supported by real product data or configured business rules.
- Wishlist and compare actions.
- Quick view and add-to-cart actions where supported.

Do not distort product images. Use appropriate aspect ratios and `object-fit: contain` for products that need to retain their full shape.

Keep titles aligned despite different lengths. Ensure badges do not obscure images or product details.

## 5. Functional integration

- Add-to-cart must use the real OpenCart cart action.
- Product options and required selections must be respected.
- Wishlist and compare must use existing mechanisms.
- Sorting and pagination must work server-side.
- Filters must produce accurate results.
- Prices, tax display, currency, and stock status must come from OpenCart.
- Display clear loading, empty, and error states where appropriate.

Do not implement server-sensitive behavior solely in client-side JavaScript.

## 6. Performance and accessibility

- Avoid duplicate queries and repeated asset loads.
- Lazy-load images below the fold.
- Use meaningful image alt text.
- Ensure filter controls have labels and keyboard support.
- Maintain visible focus states.
- Avoid layout shifts and horizontal overflow.

## Acceptance criteria

- Catalog layout resembles the reference.
- Product cards show accurate data.
- Filters, sort, pagination, wishlist, compare, and cart actions work where enabled.
- Missing filter data is handled honestly and documented.
- Category URLs and existing routes remain functional.
- Report changed files and tests performed.
