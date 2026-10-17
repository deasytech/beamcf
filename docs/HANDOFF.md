# BeamCF Storefront Theme: Phase 8 QA and Handoff

Date: 2026-10-17. Environment: local (Herd, nginx, PHP 8.3.30, MySQL `beamcf_db`, `http://beamcf.test`). Branch: `phase-7-responsive-a11y`.

## 1. What was built

A separate OpenCart 4.x theme extension at `extension/beamcf/` (theme code `beamcf`). It covers the shared header, menu, search, footer and cart drawer, plus the homepage, category listing with filters, product card, quick view, product page, cart, checkout, login, returns form and contact page.

## 2. Files

- All theme code lives under `extension/beamcf/`: 5 admin files, about 30 catalog files and 2 system files (SEO helper and CLI backfill). Run `git ls-files extension/beamcf` for the list.
- Outside the extension, the only tracked changes vs `main` are:
  - `.htaccess.txt` renamed to `.htaccess` (enables SEO URL rewrite).
  - `.gitignore`, `CLAUDE.md` and `docs/`.
  - Catalog images under `image/catalog/`.
- **No OpenCart core files and no default theme files were modified.** `git diff main...HEAD -- catalog admin/view system/library system/engine` is empty.
- `config.php` and `admin/config.php` are git-ignored and contain local DB credentials. No secrets exist in the theme's JS, CSS or Twig.

## 3. Activation and rollback

- **Activate:** Admin > Extensions > Extensions > Theme > install and enable "BeamCF Storefront Theme". Then Settings > Option > Theme > select BeamCF. Installing registers eight events: theme view routing, product list, product total, pagination, admin login logo, product SEO keyword, and two that delete a product's image files when the product is deleted (`beamcf_product_images_collect` and `beamcf_product_images_remove`).
- **Switch back to the default theme:** Settings > Option > Theme > select "Default". Nothing else is needed. The default theme files were never touched.
- **Full removal:** uninstall the theme extension. This deletes its events, and the extension directory can then be removed.
- **After changes:** clear the theme and Twig cache from Admin (Dashboard > gear icon). CSS and JS carry a `?v=` cache-buster.
- **Backups:** back up `beamcf_db` and `image/` before deploying. Use `git` tags or branches for code.

## 4. Configuration and content steps

- Run `extension/beamcf/system/cli/generate_seo_keywords.php` once to backfill SEO keywords. New products get keywords automatically through the admin event.
- Homepage banners, categories and products come from OpenCart's own data (banners, featured products and so on). Nothing is hardcoded.
- The webserver must support the `.htaccess` rewrite rules. This is nginx on Herd locally, so production needs equivalent rules.

## 5. Tests performed (2026-10-17, local, via HTTP and database checks)

**Passed**

- PHP lint of all extension PHP files and `node --check` of all theme JS: no errors.
- Theme CSS and JS: all four assets return 200.
- Pages return 200: homepage, 2 categories, sorting, pagination, search (hit and no-hit), 2 product pages, brands, 4 information pages, compare, cart, register, contact, special, sitemap (by route).
- 404 page works for an unknown URL.
- Quick view fragment renders with a real product id and price.
- Add to cart, quantity update and removal work.
- Cart totals match OpenCart: 2 × R650 + R130 = R1,430.
- An invalid coupon is rejected with the core message.
- Guest checkout validation returns the correct field errors.
- Checkout page loads with register/guest, shipping method, payment method and confirm sections.
- The confirm fragment renders real cart lines.
- Currency switching changes product prices (ZAR, USD and XOF tested).
- Wishlist and checkout redirect (302) correctly for guests.
- `error.log` did not grow during testing.
- Test data was cleaned up. The order count is still 0, and no test cart rows remain.
- Rendered screenshots (headless Chrome) were reviewed at 1440px for the homepage, category with filters, and product page. Mobile at a true 390px was reviewed for the product, category and empty-cart pages. Result: black nav and footer, consistent grids, filter sidebar, ADD TO CART and BUY NOW, and the mobile Filters button all render correctly, with no horizontal overflow.
- Browser console on 10 pages (home, 2 categories, product, cart, contact, login, register, search, brands): no JS errors and no failed requests. The only message is Chrome's "input should have autocomplete attribute" hint on the login and register password fields.

**Not tested (do not treat as passing)**

- Side-by-side comparison against the Poco reference screenshots. They are not in the repo, so only the theme's own rendering was reviewed. Tablet (768px and 1024px) was not captured.
- Real browser interaction: quick view modal, gallery, filters UI, drawer, compare and wishlist add.
- Login, registration, account pages and order history.
- Full guest checkout through to order confirmation. No test order was placed because the local DB holds real data and no test payment method was confirmed.
- Shipping estimation and tax calculation with real addresses.
- Required product options. The tested products have none.
- Newsletter, blog and testimonials. They are not installed or not checked.
- Language switching. Only `en-gb` is enabled.
- Cross-browser, mobile device and Lighthouse runs.

## 6. Known issues and observations

- **Category tiles use OpenCart's grey placeholder.** No category has an image (all `category.image` values are empty), so the homepage "Shop by Category" tiles show the placeholder. Upload category images in Admin > Catalog > Categories.
- Password inputs on login and register lack `autocomplete` attributes (console hint only).
- Screenshot caveat: headless Chrome has a 500px minimum viewport, so mobile widths must be tested in a 390px iframe or with device emulation.

- `error.log` holds old `common/currency.php` "Undefined array key ZAR" warnings from 2026-10-16. They did not recur on 2026-10-17. Confirm that no cache state triggers them again.
- `/en-gb/information/sitemap` returns 404 because that page has no SEO keyword. `route=information/sitemap` works, and the footer link uses the route form.
- Product ids and slugs in this doc are from the current DB and may change.

## 7. Extension dependencies

None beyond core OpenCart 4.x. The theme uses the default `extension/opencart` total, shipping and payment extensions unchanged.

## 8. Core modifications

None. The `.htaccess` rename is a rewrite-config change, not a code change.

## 9. Recommended next steps

1. Manual visual QA against the screenshots at 1440, 1024, 768 and 390 px, with the browser console open.
2. Place a test order on a staging copy with a test payment method (including a product with required options).
3. Check shipping and tax with real address data.
4. Run Lighthouse and an accessibility audit.
5. Prepare production rewrite rules and the SEO backfill run.
