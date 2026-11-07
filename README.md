# KDesigns Blooms & Styles

An online storefront and admin dashboard for **KDesigns**, a floral studio in Dumaguete City that sells fresh and dried bouquets, Korean baskets, bloom boxes and glass domes. Customers browse the catalog, order and track their orders. The shop owner manages orders, stock and feedback from an admin panel.

Built with Symfony 7.4, Doctrine ORM, Twig and vanilla JavaScript.

## Features

**Storefront**
- Home, About and Catalog pages with category filtering and live stock counts
- Cart drawer and order modal with pick-up scheduling
- Payment methods: Pay at Shop, GCash, BDO and BPI, with a payment reference on the e-receipt
- Log In / Sign Up modal (JSON endpoints) with a "Remember me" option
- My Orders page with order history
- Customer feedback form, shown as testimonials once approved

**Admin dashboard** (`/admin`, `ROLE_ADMIN` only)
- Overview with key figures
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

Create a `.env.local` file (it is git-ignored) with your own settings:

```dotenv
APP_SECRET=replace-with-a-long-random-string
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/kdesigns-db?serverVersion=8.0"
```

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

## Project structure

```
src/
  Command/      app:seed
  Controller/   Home, Catalog, About, Auth, Order, Stock, Admin, Category
  Entity/       User, Product, Category, Order, Feedback
  Form/         ProductType, CategoryType
  Service/      ProductCatalog, OrderService, OrderSchedule, ProductImageUploader
templates/      Twig views (home, catalog, admin, partials)
public/         css, js, images (index.php is the entry point)
migrations/     Doctrine migrations
```

## Notes

- Uploaded product images are stored under `public/images/products`.
- Registration only creates `ROLE_USER` accounts. Admins are created with `app:seed`.
