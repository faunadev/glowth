# glowth

makeup and skincare store with a customer shop and an admin panel. php 8 + mysql, no framework.

## setup (xampp / any php host)

1. copy this folder to `htdocs/glowth`
2. open phpmyadmin and import `database/glowth.sql` (it creates the `glowth` database)
3. check the login details in `config.php` (default: user `root`, no password)
4. open `http://localhost/glowth/` for the store and `http://localhost/glowth/admin/` for the admin

admin login: `admin@glowth.com` / `admin123`. change the password after the first login (edit the row in the `users` table with a new `password_hash()` value).

## what is inside

- `index.html`, `assets/` the storefront (html, css, js talking to `api/`)
- `api/` json endpoints: products, auth, favorites, checkout, orders
- `admin/` dashboard, orders, products (with photo upload), coupons, customers, settings
- `includes/` shared php (database, sessions, csrf, pricing and coupon rules)
- `uploads/` product photos added from the admin

## coupons

- every new account gets a one-time welcome coupon (`GLOW-XXXXXX`). the percent and validity days are in admin > settings.
- admin > coupons > create coupon: percent or fixed, minimum spend, total uses, uses per customer, new customers only, one specific customer, start and end dates.
- the ticket button opens a printable coupon ticket (print or download as png). the barcode is decoration. customers use the coupon by typing its code in the bag or at checkout.
- the server checks every rule again when the order is placed. cancelling an order puts the stock back and frees the coupon.

## notes

- shop prices are always read from the database, never from the browser.
- gcash and card payments are not connected to a real gateway. those orders are only recorded.
- `includes/`, `database/` and `uploads/` have `.htaccess` files for apache. on nginx, block those folders in the server config.
