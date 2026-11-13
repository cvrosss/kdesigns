# KDesigns Blooms & Styles

An online storefront and admin dashboard for **KDesigns**, a floral studio in Dumaguete City that sells fresh and dried bouquets, Korean baskets, basket bouquets and glass domes. Customers browse the catalog, order and track their orders. The shop owner manages orders, stock and feedback from an admin panel.

Built with Symfony 7.4, Doctrine ORM, Twig and vanilla JavaScript.

## Features

**Storefront**
- Home, About and Catalog pages with category filtering and live stock counts
- Cart drawer and order modal with pick-up scheduling
- Payment methods: Pay at Shop, GCash, BDO and BPI, with a payment reference on the e-receipt
- Log In / Sign Up modal (JSON endpoints) with a "Remember me" option
- My Orders page with order history
- Customer feedback form, shown as testimonials once approved

**Roles**

| Role | Can do |
|---|---|
| Customer (`ROLE_USER`) | Browse, order, view own orders, view profile and change password (`/account`) |
| Staff (`ROLE_STAFF`) | Everything a customer can, plus view all products, add products, **edit/delete only the products they created** (`/manage/products`), manage categories (`/category`), and run day-to-day **orders** (`/admin/orders`: view all orders, update status) and **inventory** (`/admin/inventory`: restock, add products) |
| Admin (`ROLE_ADMIN`) | Everything staff can, plus the dashboard, buyers, feedback, and **user management** (`/admin/users`: create, edit, reset password, change role, disable, delete) and edit/delete any product |

Access is enforced three ways: `access_control` and `role_hierarchy` in `config/packages/security.yaml`, `#[IsGranted]` on controllers (plus `ProductVoter` for product ownership), and `is_granted()` in Twig menus. A signed-in user without permission sees a 403 page; anonymous visitors are redirected to `/login`. Disabled accounts cannot log in and are signed out on their next request.

Create a staff account with the seed command: `php bin/console app:seed --staff-email=staff@example.com --staff-password=choose-a-password`. Run `php bin/console doctrine:migrations:migrate` to add the new `user.active` and `product.created_by_id` columns.

**Admin dashboard** (`/admin`, `ROLE_ADMIN` only, except Orders and Inventory, which staff can also open)
- Overview with key figures, including total users, total staff and total records
- Orders list with status updates
- Inventory: add products with image upload and adjust stock
- Buyers history
- Feedback moderation
- Category management (`/category`)

## Tech stack

- PHP 8.2+
- Symfony 7.4 (Security, Form, Validator, Twig)
- Doctrine ORM 3 and Doctrine Migrations
- MySQL 8
- Plain CSS and JavaScript in `public/` (no build step)

## Getting started

### Requirements
- PHP 8.2 or newer with `ctype` and `iconv`
- Composer
- MySQL 8
- [Symfony CLI](https://symfony.com/download) (optional, for the local server)

### Setup

```bash
git clone https://github.com/cvrosss/kdesigns.git
cd kdesigns
composer install
```

Create a `.env.local` file (it is git-ignored) with your own settings. The committed `.env`
holds safe defaults only — it ships `APP_ENV=prod` so that nothing deployed without
configuration ever runs in debug mode, and carries no credentials:

```dotenv
APP_ENV=dev
APP_SECRET=replace-with-a-long-random-string
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/kdesigns-db?serverVersion=8.0"
```

Generate a secret with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. In production the
app refuses to start if `APP_SECRET` is empty, because it signs the session and
"remember me" cookies.

Then create the database and load the starter data:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console app:seed --admin-email=admin --admin-password=choose-a-password
```

`app:seed` loads the starter catalog, testimonials and an admin account. It is safe to run more than once. If you leave out `--admin-password`, a new admin gets a random password that is printed once.

### Run

```bash
symfony server:start
# or
php -S 127.0.0.1:8000 -t public
```

Open <http://127.0.0.1:8000>. Log in with the admin account to be taken to `/admin`.

## Run with Docker

`compose.yaml` runs the whole stack as one Docker Compose project, `kdesigns`:

| Service | Container | URL / port |
|---|---|---|
| PHP 8.3 + Apache (the shop) | `kdesigns-app` | <http://localhost:8001> |
| MySQL 8 | `kdesigns-mysql` | `localhost:3307` |
| phpMyAdmin | `kdesigns-phpmyadmin` | <http://localhost:8080> |

```bash
cp .env.docker.example .env.docker      # then set your own passwords and APP_SECRET
docker volume create kdesigns-db-data   # first time only; keeps your data safe from `down -v`
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console app:seed --admin-email=admin --admin-password=choose-a-password
```

Stop it with `docker compose down`. The project folder is mounted into the container, so code edits show up immediately.

## Deploying to production

`compose.yaml` above is the **development** stack: it runs in debug mode, mounts your source
tree, exposes MySQL on a host port and ships phpMyAdmin. Do not put it on the internet.

Use `compose.prod.yaml` instead. It builds the image with `APP_ENV=prod`, bakes the code in,
keeps the database on the internal network only, drops phpMyAdmin, and connects as a
least-privilege database user:

```bash
cp .env.docker.prod.example .env.docker.prod   # then replace every value
docker compose -f compose.prod.yaml --env-file .env.docker.prod up -d --build
docker compose -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate
docker compose -f compose.prod.yaml exec app php bin/console app:seed --admin-email=admin
```

The container listens on `127.0.0.1:8000`. Put a TLS-terminating reverse proxy (Caddy, nginx,
Traefik) in front of it — several protections, HSTS among them, only engage over HTTPS.

Security applied at this layer: `ServerTokens Prod` and `expose_php=Off` hide version banners;
`docker/apache.conf` sets CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`
and `Permissions-Policy`; logs go to stderr as JSON for `docker compose logs`.

Deploying to ordinary shared hosting instead? `public/.htaccess` carries the equivalent
rewrite and header rules, and the document root must be `public/`.

## Tests

```bash
php bin/console --env=test doctrine:database:create --if-not-exists
php bin/console --env=test doctrine:migrations:migrate
php bin/phpunit
```

Put your test database credentials in `.env.test.local` (git-ignored). The suite covers the
role boundaries, order isolation between customers, catalog visibility, product ownership,
and the stock and ordering-cutoff rules.

## Project structure

```
src/
  Command/      app:seed
  Controller/   Home, Catalog, About, Auth, Account, Order, Stock, Admin, ManageProduct, User, Category
  Entity/       User, Product, Category, Order, Feedback
  Form/         ProductType, CategoryType, UserType, ChangePasswordType
  Security/     ProductVoter, UserChecker, LoginEntryPoint, AccessDeniedHandler
  Service/      ProductCatalog, OrderService, OrderSchedule, ProductImageUploader
templates/      Twig views (home, catalog, admin, manage, account, partials)
tests/          PHPUnit suite (Unit + Functional)
public/         css, js, images (index.php is the entry point)
migrations/     Doctrine migrations
docker/         apache.conf, php.ini used by the image
```

## Notes

- Uploaded product images are stored under `public/images/products`, and are deleted from disk when the product is deleted or its photo replaced.
- Registration only creates `ROLE_USER` accounts. Admins are created with `app:seed`.
- An account that has placed orders cannot be deleted, because the order rows carry the shop's sales history. Disable it instead — the person can no longer log in and the records are kept.
- Failed logins are rate limited to 5 per minute per account.
- The order modal and cart drawer use a few Tailwind utilities, compiled once into `public/css/tailwind.css`. Regenerate it only after adding new utility classes: `npx tailwindcss@3.4.17 -c tailwind.config.js -o public/css/tailwind.css --minify`.
- The studio's social links live in `src/Twig/StorefrontExtension.php`. Any entry left as `'#'` is rendered in the footer as plain text rather than as a dead link.
