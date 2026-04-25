lakaylink
=====

Marketplace Allows expat family members to purchase food items (for example rice 1kg, rice 2kg, oil 1 liter, oil 2 liters) and have it delivered to their family back home in Africa. 


## contents

This project sets up a Drupal Commerce system with:

* Anonymous product browsing
* Role-based purchasing
* Google Sheet product import
* Google social login
* Stripe integration

---

### 1. Anonymous Access

* Anonymous users can:

  * View `/stores` (temporary view)
  * View `/products` (temporary view)
  * Filter products by store

* Anonymous users **cannot purchase products**
* Cannot see Add to Cart

---

### 2. Buyer Role Permissions

* Only users with **"buyer"** role can:

  * Add products to cart
  * Complete checkout
  * Checkout flow validates role condition
  * Default role for new users = **buyer**

---

### 3. Google Login

* Enabled using Drupal Social Auth Google
* Users can log in via Google
* `/user/login` redirects to `/user/login/google`
* Requires Google API configuration

  Installed & enabled:
  Social Auth Google

  Configure Google credentials:
  Created project in Google Cloud
  Added Client ID & Secret in Drupal

  Enabled redirect module
  Added redirects:
  /user/login → /user/login/google
  /user/password → /user/login/google


  ==> I have enabled social auth google module. For login with google to work we have to make we have to make a configuration setup mentioned in the https://www.drupal.org/project/social_auth_google/ .

  ⚠️ Needs verification in dev environment



---

### 4. Stripe Integration

  * Managed using Drupal Commerce Stripe
  * Admins can configure Stripe payments
    
  configure module follow these steps  https://git.drupalcode.org/project/commerce_stripe#configuration
  http://localhost:50281/admin/commerce/config/payment-gateways/manage/stripe_card_element?destination=/admin/commerce/config/payment-gateways

  configure at /admin/commerce/config/payment-gateways/add

---

### 5. Product Import (Google Sheets)

Admins can import products using:

```
drush my-custom-module:import 1
```

Or via cron.



---

## Grocery Import System

### Store Setup

Create a store at:

```
/store/add/online
```

Required fields:

* Store name
* Email
* Currency
* Timezone
* Address
* Google Sheet URL (published)
* Google Sheet Tab GID

---

### Google Sheet Setup

* Create a sheet (example: https://docs.google.com/spreadsheets/d/12x-ANhpnkr_QO8QmXhWKdoCUA-qMRaxDh0wT-yLSqAI/edit?usp=sharing)
* Add required headers

* Publish to web:
  * File → Share → Publish to web -> select csv format . copy the url and add it to the store.
  * Tab GID :- each tab has its gid. you can check in url google sheet query paramter.

---

### Required CSV Columns

* `product_id`
* `product_name`
* `variation_sku`
* price, currency, quantity
* brand, category, sub_category
* image_url, description, stock,

---

### How Import Works

#### 1. Cron Job

* Loads all stores
* Adds each store to queue

#### 2. Queue Worker

* Processes one store at a time
* Calls import function

#### 3. Import Process

* Fetch CSV from Google Sheets
* Parse data
* Create/update:

  * Products
  * Variations
  * Taxonomy (brand, category)
  * Images

---

### Run Import

Using cron:

```
drush cron
```

Example cron job:

```
*/5 * * * * /usr/local/bin/drush -r /var/www/html/drupal cron -q
```

---

### Logging

Logs available for:

* Import status
* Errors
* Skipped rows
* SKU processing

---

## Notes

* Each store uses its own Google Sheet
* Ensure sheet is **published to web with CSV format**
