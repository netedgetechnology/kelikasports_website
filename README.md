# KELIKA Website & CMS

Custom Laravel-based website and section-based CMS for KELIKA.

## Locked project decisions

- Linux hosting with cPanel.
- PHP 8.3 or higher; Laravel 13.
- MySQL or a supported MariaDB LTS release.
- Blade templates, HTML5, CSS3, Bootstrap 5, JavaScript.
- Database-driven section-based page builder.
- Court booking buttons are configurable links only. No booking engine or payments are included.
- No Docker and no persistent Node.js process in production.
- A fresh installation publishes the eight starter pages and core sport/program/facility content so the site displays immediately. Review and edit the starter copy and illustrative stock imagery before public launch; do not present stock images as actual KELIKA facilities or people.

## Pages scaffolded

Home, Academy, Sports, Programs, Facilities, Events, About, Contact.

## Requirements

- PHP 8.3+ and the PHP extensions required by Laravel 13, including PDO MySQL, Mbstring, OpenSSL, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo, and cURL.
- Composer 2.
- MySQL/MariaDB database and database credentials.
- Node.js/npm is optional for building optimized frontend assets. A static fallback stylesheet/script is included, so the website and CMS can render without running Vite on the server; Bootstrap fallback assets are served from jsDelivr and therefore require outbound internet access. Node.js is not required as a persistent production service.
- SMTP details for password reset and enquiry notifications.

Confirm the exact PHP version, extensions, and database version available in cPanel before deployment. Do not silently lower the project's PHP requirement to accommodate a different host.

## Local setup

Run these commands from the project root in a PHP 8.3+ environment:

```bash
cp .env.example .env
composer install  # generates composer.lock; commit the lock file after dependency review
php artisan key:generate
npm install  # optional, for optimized local assets
npm run build  # optional; without this, the included static fallback assets are used
```

Create an empty database and update `.env` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Configure the site URL and SMTP details as well.

Before seeding, configure a secure initial administrator in `.env`:

```dotenv
KELIKA_ADMIN_NAME="KELIKA Administrator"
KELIKA_ADMIN_EMAIL="your-real-admin-email@example.com"
KELIKA_ADMIN_PASSWORD="use-a-unique-password-of-at-least-14-characters"
```

Do not use the example values or commit `.env` to source control. Then run:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan optimize:clear
```

Start the local server:

```bash
php artisan serve
```

Open `/admin/login` to sign in. If no admin account is created, confirm the three `KELIKA_ADMIN_*` variables are set in `.env` and rerun `php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder`.

## cPanel deployment notes

1. Create a database and database user in cPanel, then grant the required privileges.
2. Upload the project with private application files outside the public document root wherever hosting permits.
3. Point the domain/subdomain document root to the Laravel `public/` directory. Do not expose the project root, `.env`, `vendor`, private storage, or backups to the web.
4. Set `.env` with `APP_ENV=production`, `APP_DEBUG=false`, a generated `APP_KEY`, the correct `APP_URL`, database credentials, SMTP credentials, and a secure initial administrator.
5. Install Composer dependencies in a PHP 8.3+ environment. If shell access is unavailable, build dependencies and assets in a compatible environment and upload the deployment package securely; never upload `.env` from a development machine with unrelated credentials.
6. Optionally build optimized frontend assets with `npm install && npm run build`. If `public/build/manifest.json` is absent, the included fallback CSS/JS is used and Bootstrap is loaded from jsDelivr; no Node process is needed in production.
7. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP process. Create the public storage link if supported by the host.
8. Run `php artisan migrate --seed` only after backing up any existing database. Do not run destructive migrations on production without explicit approval.
9. Configure SSL, SMTP, backups, log rotation, and a documented restore procedure.
10. Verify the site and admin panel over HTTPS, then test a contact submission, password reset, uploads, sitemap, robots.txt, publishing, and the mobile layout.

If cPanel does not allow the document root to point to Laravel's `public/`, use a secure deployment layout that keeps the application and `.env` outside `public_html`, and adjust only the public entry point paths. Do not simply copy the whole Laravel project into a publicly browsable directory.

## CMS usage overview

- **Pages & Sections:** create pages, edit SEO, add section types, configure section content, reorder sections with up/down controls, duplicate, hide, preview, publish/unpublish.
- **Media Library:** upload approved images and copy the generated media path into section/content settings. Replace media references before deleting an asset.
- **Menus:** maintain the `main` and `footer` menus. Use safe relative paths such as `/sports` for internal links.
- **Global Settings:** manage branding, element colors, logos, contact details, social profiles, and the future booking URL.
- **Content:** manage sports, programs, facilities, events, coaches, FAQs, galleries, and testimonials. Use drafts until facts and images are approved.
- **Enquiries:** review submissions, update status, keep internal notes private, and export CSV when needed.
- **URL Redirects:** create redirects for retired/changed paths.
- **CMS Users:** administrator-only user management; editors cannot manage user accounts.

## Design and content rules

See `docs/ASSET_INVENTORY.md` for the supplied visual references and the starter stock imagery used by the site. Full-page screenshots are reference material only and must not be sliced into production photographs. Do not publish fabricated stats, testimonials, events, credentials, facility claims, prices, email addresses, or address/map information. The specified phone number is `7874028876`; confirm any changes before launch.

## Current status and known limits

See `docs/IMPLEMENTATION_STATUS.md`. The source scaffold has been syntax-checked, but the Laravel runtime, migrations, Blade compilation, asset build, automated tests, and browser testing still need to run in a matching PHP 8.3+ environment. Do not treat this package as production-ready until those verification gates pass.
