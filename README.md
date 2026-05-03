lakaylink
=====

Marketplace Allows expat family members to purchase food items (for example rice 1kg, rice 2kg, oil 1 liter, oil 2 liters) and have it delivered to their family back home in Africa. 


## contents

* Quickstart

- This project sets up a Drupal Commerce system with:

* Anonymous product browsing
* Role-based purchasing ("buyer" can only purchase)
* Import products and variations from Google Sheet 
* Google social login
* Stripe integration
* api setup

---

Quickstart
-----

Step 1:
  
    git clone https://github.com/alberto56/lakaylink.git
    cd lakaylink

Step 2: update .env files

    VIRTUAL_HOST=
    GOOGLE_CLIENT_ID=
    GOOGLE_CLIENT_SECRET=
    STRIPE_PUBLISHABLE_KEY=
    STRIPE_SECRET_KEY=
    WEBHOOK_SIGNING_SECRET=

    refer 
    - [refer step0, step1 and step2 to get client id and client secret](readme/social-auth-google-configuration.md)
    - [refer stripe setup to get publishable key, secret key, webhook signing secret](readme/drupal-coomerce-stripe-connect.md)

    - create oauth public and private keys.

      * openssl genrsa -out drupal/starter-data/private-files/social-auth-oauth-keys/private.key  2048

      * openssl rsa -in drupal/starter-data/private-files/social-auth-oauth-keys/private.key -pubout -out drupal/starter-data/private-files/social-auth-oauth-keys/public.key

    ./scripts/deploy.sh


### 1. Anonymous Access

* Anonymous users can:

  * View `/stores` (temporary view)
  * View `/products` (temporary view)
  * Filter products by store id

* Anonymous users **cannot purchase products**
* Cannot see Add to Cart button

---

### 2. Buyer Role Permissions

* Only users with **"buyer"** role can:

  * Add products to cart
  * Complete checkout
  * Checkout flow validates role condition
  * Default role for new users = **buyer**

---

### 3. Google Login

* Enabled redirect module
  Added redirects:
  /user/login → /user/login/google
  /user/password → /user/login/google


* Enabled Drupal Social Auth Google module
* Users can log in via Google

* Requires Google API configuration
  we have to pass GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to drupal server.

- [Follow Google social login configuration setup](readme/social-auth-google-configuration.md)

---

### 4. Stripe Integration

  * Managed using Drupal Commerce Stripe module
  * Admins can configure Stripe payments.

  configure module follow these steps  https://git.drupalcode.org/project/commerce_stripe#configuration
  http://localhost:50281/admin/commerce/config/payment-gateways/manage/stripe_card_element?destination=/admin/commerce/config/payment-gateways

  configure at /admin/commerce/config/payment-gateways/add

  - [Stripe setup (Drupal Commerce)](readme/drupal-coomerce-stripe-connect.md)

---

### 5. Product Import (Google Sheets)

Admins can import products using:

```
drush my-custom-module:import 1
```

Or via cron.



---

## Grocery Import System

### Google Sheet Setup

* Create a google sheetedit?usp=sharing)
* copy headers from ( https://docs.google.com/spreadsheets/d/12x-ANhpnkr_QO8QmXhWKdoCUA-qMRaxDh0wT-yLSqAI/ )

* Publish to web:
  * File → Share → Publish to web -> select csv format . copy the url and add it to the store.
  * Tab GID :- each tab has its gid. you can check in url google sheet query paramter.

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

- [Refer Importing products and variants](readme/importing-products-variants.md)

# API Setup
----

- [api setup](readme/api-setup.md)
