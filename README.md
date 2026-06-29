lakaylink
=====

Marketplace Allows expat family members to purchase food items (for example rice 1kg, rice 2kg, oil 1 liter, oil 2 liters) and have it delivered to their family back home in Africa. 


## contents

* Quickstart

- This project sets up a Drupal Commerce system with:

* Role-based purchasing ("buyer" can only purchase)
* Import products and variations from Google Sheet 
* Google social login
* Stripe integration
* api setup
* Import - export menu links, taxonomy terms, block content

---

Quickstart
-----

Step 1: update .env files

    VIRTUAL_HOST=
    GOOGLE_CLIENT_ID=
    GOOGLE_CLIENT_SECRET=
    STRIPE_PUBLISHABLE_KEY=
    STRIPE_SECRET_KEY=
    WEBHOOK_SIGNING_SECRET=

    refer 
    - [refer step0, step1 and step2 to get client id and client secret](readme/social-auth-google-configuration.md)
    - [refer stripe setup to get publishable key, secret key, webhook signing secret](readme/drupal-coomerce-stripe-connect.md)

    - create public and private keys to setup simple oauth.

      * openssl genrsa -out drupal/starter-data/private-files/social-auth-oauth-keys/private.key  2048

      * openssl rsa -in drupal/starter-data/private-files/social-auth-oauth-keys/private.key -pubout -out drupal/starter-data/private-files/social-auth-oauth-keys/public.key

    ./scripts/deploy.sh


### 1. Anonymous Access

* Anonymous users only sees /custom-login page

* Anonymous users **cannot purchase products**
* Cannot see Add to Cart button

---

### 2. Seller

Seller google sign into the system from /custom-login page.
First he will be the unverified user hence he will see /account/buyer-verification form.

administrator update seller from unverified to admin (remove unverified role and select admin)
 from seller account edit page.

Now seller has to logout and login to system again.

He will see the shops and generate code button respectively.

*** Right now sellers sees all the stores. Currently Administrator is creating the store hence administrator is
the owner of the store. ( we have to give permissions to seller to create their own stores then we can list
seller specific stores).


clik on generate code , you will get some thing like this.

1783501414/2/d67eae76e057b82ba7d6a2dfc07d84853faf924d3d8f6e20c7f910e848c9b698 

*** code is not user specific any user with this code can now use it. ***

Expiration time 2 minutes. If required we can chage it at drupal/custom-modules/my_custom_module/src/Controller/GenerateCodeController.php in in generate method.


### 2. Buyer Role Permissions

  Buyer google sign into the system from /custom-login page.

  - First he will be the unverified user hence he will see /account/buyer-verification form.

    buyer-verification page that says:

    * Contact us by WhatsApp at 555-555-5555 to confirm that your family's location is covered by our service
    * We will give you a confirmation code by whatsApp
    * Enter that confirmation code here: (and there is a field)

  - Buyer has to contact seller by whatsapp and get confirmation code.

  - seller confirms that the buyer is eligible

  - Paste confirmation code in verification text field and submit,
    once the unverified user verification confirmed his role gets updated to buyer and
    he will be allowed to access the store.

    If buyer wants to access someother store then go to /account/buyer-verification  form
    and add confirmation code and submit.

  - Next time when buyer logs in If he dont have access to any store then he will see 403 page,
    if he has access to only one store then he will be redirected to that particular store page,
    If he has access to multiple pages then he will see the store list in select-store page.

* Only users with **"buyer"** role can:

  * Add products to cart
  * Complete checkout
  * Checkout flow validates role condition buyer

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

# 5. Product, product variation, product variation image Import (Google Sheets)


### Google Sheet Setup

* Create a google sheetedit?usp=sharing)
* copy headers from ( https://docs.google.com/spreadsheets/d/12x-ANhpnkr_QO8QmXhWKdoCUA-qMRaxDh0wT-yLSqAI/ )
* create tab for each store. Ex:- HAITI_USA, CAMEROON_USA, TOGO_USA ...... while importing each product
  we are mapping tab name with store name, adding products to those stores.

  ```
  product_id,	product_description,	product_name,	lang,	category,	category_code,	sub_category,	sub_category_code,	brand,	brand_code,	pack_type,	variation_sku,	variant_name,	quantity,	unit,	price	currency,	stock,	weight,	unit_type,	origin,	expiry_days,	storage_type,	image_url,	status
  P1,	,	Cumin2,		Spices,	Spices,	Spices,	Spices,	Tata,	Tata,	unpacked,	CUM-1,Cumin 2pcs,	2	pcs,475,USD,630,2	pcs,	India,	85,	cold,	https://<your image url>,	1
  ```

* Publish to web:
  * File → Share → Publish to web -> select csv format . copy the url and add it to the store (refer Store Setup).
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


*** It is mandatory to create landing page for each store, otherwise buyer cannot see the stores. ***

  go to /add/node/homepage

  Title : ex- Haiti Home
  Store Reference:- Select store, ex:-  Haiti USA
  URL alias :- Patters /store/<store slug>/<store_id> ex:- /store/haiti-usa/1

  Page Sections select Carousel and create carousel slides.

  Product List only one block is added (view block) select Shop .

  save the page.

  ** pathauto module has a issue, I have uninstalled it**

  We have to maintain url alias of a home page as mentioned above, otherwise product listing block cannot
  list stores specific products in stores landing page. 

---

- [Refer Importing products and variants for more info](readme/importing-products-variants.md)


  Admins can import products using:

  ```
  drush my-custom-module:import 1
  ```

  Or via cron.


  ### Ex:- How to import an image for a product variant
  —---

  For example, let’s say you have a product “Olive oil” with a 500ml variant and a 3 liter variant, and you have two pictures, oil500.jpg and oil3l.jpg, 

  (1) step 1, make sure the images are available online at http://example.com/whatever/oil500.jpg and http://example.com/whatever/oil3l.jpg

  (2) make a copy of the sample google sheet https://docs.google.com/spreadsheets/d/12x-ANhpnkr_QO8QmXhWKdoCUA-qMRaxDh0wT-yLSqAI/ 

  (3) create or edit exist store tab in the google sheet, suppose your store is haiti usa then in google sheet edit
    haiti_usa tab.

  (4) Remove all the products except headers. Fill the row. Add product ( Each variant one row).

    ```
    product_id,product_name,category,brand,pack_type,variation_sku,variant_name,quantity,unit,price,currency,stock,weight,unit_type,origin,expiry_days,storage_type,image_url,status

    P2,Olive Oil,Spices,Generic,bottled,OLV-500,500 ml,500,ml,300,USD,120,500,ml,India,365,normal,http://example.com/whatever/oil500.jpg,1
    P2,Olive Oil,Spices,Generic,bottled,OLV-3000,3 liter,3,l,1500,USD,40,3000,ml,India,365,normal,ttp://example.com/whatever/oil3l.jpg,1

    ```

  (3) Log into the back-end as an admin and go to /admin/commerce/config/store
  (4) select a store or create one .
  (5) make sure the store has your google sheet as a source and add Tab id
  (6) run drush cron


# API Setup

- [api setup](readme/api-setup.md)


# Import - export menu links, taxonomy terms, block content

  ### Export content.
  - If you have added any menu links, taxonomy terms, block content in local.
  - Go to /admin/structure/structure-sync/general , go to respective tab (menu links, taxonomy terms, custom block).
  - click on Select the vocabularies/custom block/ menus you would like to export
  - export configuration and commit file changes to git.

  ### Import content.
  - If you have want to import any menu links, taxonomy terms, block content from local.
  - Go to /admin/structure/structure-sync/general , go to respective tab (menu links, taxonomy terms, custom block).
  - click on Select the vocabularies/custom block/ menus you would like to import
 