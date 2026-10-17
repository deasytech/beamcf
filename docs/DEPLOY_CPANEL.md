# Deploying BeamCF (OpenCart 4.x) to cPanel

Two packages are built locally into `deploy/` (git-ignored; rebuild with the commands in section 9):

| File | Contents | Goes to |
|---|---|---|
| `beamcf-deploy.zip` | All tracked code, the `extension/` folders (beamcf, paystack, geozone), images and `.htaccess`. No `config.php`, no secrets, no docs. | `public_html/` |
| `beamcf-storage.zip` | `vendor/` (Twig and other libraries OpenCart loads from the storage folder; it is not in git). | `/home/CPANELUSER/storage/` |

Replace `CPANELUSER`, `yourdomain.com` and the database names below with your own values.

## 1. Prerequisites
- cPanel with **PHP 8.1 or newer** (the site runs on 8.3 locally): *MultiPHP Manager* > select the domain > PHP 8.2/8.3.
- PHP extensions on (*MultiPHP INI Editor* / *Select PHP Version*): `mysqli`, `curl`, `gd`, `zip`, `mbstring`, `openssl`, `xml`, `zlib`.
- Apache with `mod_rewrite` (standard on cPanel). SSL installed (AutoSSL) so Paystack and checkout run over HTTPS.
- Recommended PHP values: `memory_limit 256M`, `upload_max_filesize 20M`, `post_max_size 20M`, `max_execution_time 60`.

## 2. Export the local database
On your Mac (database `beamcf_db`):
```
mysqldump -u root -p --single-transaction --default-character-set=utf8mb4 beamcf_db > beamcf_db.sql
```
Do **not** commit this file; it contains customers and orders. Take a copy of `image/` too if it has files that are not in git (the zip contains only tracked images).

## 3. Create the database (cPanel > MySQL Databases)
1. Create database `CPANELUSER_beamcf`.
2. Create user `CPANELUSER_beamcf` with a strong password.
3. Add the user to the database with **ALL PRIVILEGES**.

## 4. Import the database (cPanel > phpMyAdmin)
Select the new database > *Import* > choose `beamcf_db.sql`. If the file is larger than the upload limit, gzip it first (`gzip beamcf_db.sql`) or import through the *Terminal* with `mysql -u USER -p DBNAME < beamcf_db.sql`.

Then fix the store URLs. In OpenCart 4 the store URL comes from `config.php`, but the SEO URLs and any stored absolute links should be checked. In phpMyAdmin run (adjust the prefix if you use one; this install uses none):
```sql
SELECT `key`, `value` FROM setting WHERE `value` LIKE '%beamcf.test%';
```
Update any rows returned to `https://yourdomain.com/`.

## 5. Upload the code
1. cPanel > *File Manager* > open `public_html` (for a subdomain use its document root).
2. *Upload* `beamcf-deploy.zip`, then right-click > *Extract* into `public_html`. Delete the zip afterwards.
3. Confirm `public_html/.htaccess`, `index.php`, `admin/`, `catalog/`, `extension/`, `image/`, `system/` exist.

## 6. Storage folder (outside the web root)
1. In *File Manager* go to `/home/CPANELUSER/` (above `public_html`) and create `storage`.
2. Inside it create folders: `cache`, `download`, `logs`, `session`, `upload`, `backup`, `marketplace`.
3. Upload and extract `beamcf-storage.zip` into `storage/` so you get `storage/vendor/autoload.php`.
4. Permissions: folders `755` (or `775` if PHP runs as a different user), files `644`.

## 7. Create the config files
1. Copy `docs/deploy/config.php.sample` to `public_html/config.php` and `docs/deploy/admin-config.php.sample` to `public_html/admin/config.php` (upload or edit in File Manager).
2. Fill in the real domain, home path, and database name, user and password.
3. Set both files to permission `640` or `600`. Never commit them (they are git-ignored).

## 8. Activate and verify
1. Open `https://yourdomain.com/admin/` and log in (the admin credentials come from the imported database).
2. If the theme or extensions are already marked installed in the imported database, you are done. Otherwise: *Extensions > Extensions*:
   - *Theme*: install **BeamCF Storefront Theme**.
   - *Shipping*: install **Geo Zone** shipping.
   - *Payments*: install **Paystack**, enter the **live** keys (test keys while testing) and set the statuses.
3. *System > Settings > Edit store > Option > Theme*: select **BeamCF**. To fall back at any time, select **Default**.
4. Clear caches: *Dashboard > gear icon* > refresh theme and Twig cache.
5. Run the SEO keyword backfill once (cPanel Terminal): `php /home/CPANELUSER/public_html/extension/beamcf/system/cli/generate_seo_keywords.php`.
6. Smoke test: home, a category, a product, add to cart, checkout page, login and **My Account** (new dashboard and sidebar), order history. Make a **test** Paystack payment before going live. Check `storage/logs/error.log` is not growing.

## 9. Rebuilding the packages
From the repo root after committing:
```
git archive --format=zip -o deploy/beamcf-deploy.zip HEAD -- . ':!docs' ':!CLAUDE.md' ':!php.ini'
(cd /path/to/storage && zip -qr /path/to/repo/deploy/beamcf-storage.zip vendor -x "*.DS_Store")
```
For later updates upload only the changed files (or the whole zip over the existing site) and clear the theme/Twig cache. `config.php` files are not in the zip, so they are never overwritten.

## 10. Troubleshooting
- **500 error**: PHP version too old, or `Options +FollowSymlinks` not allowed. Comment that line in `.htaccess`.
- **Blank page / "Class Twig not found"**: `storage/vendor` missing or `DIR_STORAGE` wrong.
- **Pretty URLs 404**: confirm `.htaccess` is present (dotfiles are hidden; enable *Show Hidden Files*) and `RewriteBase /` matches the install folder.
- **Cannot save in admin / session errors**: `storage/session` and `storage/cache` not writable.
- **Images missing**: `image/` incomplete; upload the missing folders and keep `image/cache` writable.
- **Paystack callbacks fail**: the site must be HTTPS and the live keys saved; check `storage/logs/error.log`.

## 11. Rollback
Keep the previous `public_html` as a zip and a database export before each deploy. To roll back, restore both. To disable only the theme, set *Theme* back to **Default** (no files need removing).
