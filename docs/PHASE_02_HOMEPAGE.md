# Phase 2: Poco-Inspired Homepage

## Goal

Build the homepage using the supplied Poco Mega Store screenshot as the visual reference and the existing OpenCart catalog as the source of truth.

## Prerequisite

Phase 1 is complete and the shared header, navigation, design tokens, and footer are working.

## Required sections

### 1. Hero carousel

Create a wide promotional slider with:

- Large editorial or product image.
- Heading and short supporting copy.
- Primary Shop Now action.
- Slide indicators and previous/next controls.

Reuse configured OpenCart banners or existing store content where possible. If the current project does not provide editable slides, use a maintainable configuration or supported module approach and document how the store owner can update them. Do not invent a new admin feature unless necessary.

### 2. Promotional banner pair

Display two side-by-side campaign tiles with imagery, heading, optional copy, and CTA. Stack them responsively on small screens.

### 3. Store benefits

Create a row of four compact benefits inspired by the screenshot:

- Free Shipping.
- 24/7 Support.
- 30 Days Return.
- Secure Payment.

Only display promises that reflect the actual store policies. Make content configurable where practical.

### 4. Deals of the Day

Build a promotional area containing a main campaign tile, a featured deal, and a secondary promotional tile.

Show real product information and pricing. Display a countdown only when a real expiry timestamp exists. Do not reset a countdown on reload or invent sale deadlines.

### 5. Shop by Category

Display a grid of category tiles with category images, names, and links. Use actual category data. Add a fallback image when a category image is missing.

### 6. Product showcase tabs

Create Latest, Featured, and Best Seller tabs or equivalent sections.

- Latest should use a valid latest-product data source.
- Featured should use the project's configured featured products or existing featured module.
- Best Seller should use an existing supported data source or extension.

Do not label products as featured or best sellers without a data source that justifies the label.

Each product card should show the correct product image and name, current price, applicable special price, previous price when discounted, rating when reviews exist, and justified badges. Provide functional wishlist, compare, quick view, and add-to-cart actions where supported.

### 7. Campaign banner

Add a wide dark promotional banner with a campaign heading, supporting copy, image, and Shop Now link. Keep content easy to replace.

### 8. Special products and best sellers

Build a balanced mixed layout with product cards and a larger campaign tile. Use real catalog data and keep the layout responsive.

### 9. Trending products

Create a light-gray product showcase area. Use a grid or accessible carousel and existing product data. Do not invent a trending classification if no valid source exists.

### 10. Testimonials and blog

Show testimonials only if real testimonial data or an installed extension exists. Show recent blog posts only if a blog extension or existing blog route is available.

If unsupported, omit the section cleanly or leave it configurable and document the dependency. Never fabricate customer quotes, identities, article content, or ratings.

### 11. Brand strip

Display configured manufacturer names or logos when available. Each item must link to a valid manufacturer page where supported.

## Implementation rules

- Use existing OpenCart modules, models, and routes when suitable.
- Do not hardcode products, prices, product IDs, ratings, categories, or stock.
- Avoid duplicate database queries and unnecessary API requests.
- Ensure all CTAs have meaningful destinations.
- Use consistent product card components.
- Optimize banner images and lazy-load below-the-fold images.
- Preserve the existing homepage route and store configuration.

## Acceptance criteria

- All available sections render without template or JavaScript errors.
- Product names and images correspond to the same catalog records.
- Prices and discounts use OpenCart formatting and data.
- Unsupported modules do not produce broken or fake content.
- CTAs and product actions work.
- Desktop layout follows the reference; tablet and mobile layouts remain usable.
- Report files changed, data sources used, and unavailable features.
