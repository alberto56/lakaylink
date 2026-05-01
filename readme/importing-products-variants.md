# Grocery Import Module (Drupal Commerce)

This module imports product and variation data from a Google Sheet (CSV) into Drupal Commerce.

It is designed for:

* Multi-store setup
* Automated imports via cron + queue
* Safe handling of CSV, images, and taxonomy

---

## 1. Overview

The module:

# 🚀 How to run this module
1. Reads a Google Sheet (CSV)
2. Validates and parses data
3. Creates or updates:

   * Products
   * Variations
4. Downloads product images
5. Assigns taxonomy (brand, category, etc.)
6. Runs automatically via cron + queue

---

# 🚀 How to run this module

## 1. Run via Drupal cron (main method)

Your flow starts here:

```php id="c1k2x9"
function my_custom_module_cron() {
  App::instance()->hookCron();
}
```

### What happens:

1. Cron runs
2. All store IDs are fetched
3. Each store is pushed into a queue

---

## Run cron manually

### Drush:

```
drush cron
```

Or:

```
drush core:cron
```

Example cron job:

```
*/5 * * * * /usr/local/bin/drush -r /var/www/html/drupal cron -q
```


* Loads all stores
* Adds each store to queue

---

### UI:

```
/admin/config/system/cron
```

Click **Run cron**

---

# 2. Queue processing (actual import step)

Cron only **queues store IDs**.

Actual import happens when queue worker runs:

👉 Queue: `my_custom_module_import_queue`

* Processes one store at a time
* Calls import function

---

## Run queue manually

If you use Drush queue worker:

```
drush queue-run my_custom_module_import_queue
```

---

# 3. What actually happens step-by-step

### When cron runs:

* Gets all stores
* Adds store_id to queue

### When queue runs:

For each store:

1. `my_custom_module_import($store_id)` executes
2. Google Sheet CSV is fetched
3. CSV parsed
4. Products created/updated
5. Variations updated
6. Images downloaded

---

# 4. Important answer to your question

## ❓ “If I add rows, will it import automatically?”

### ✅ Yes — BUT ONLY IF:

* Cron runs
* Queue worker runs
* Google Sheet is updated

---

### ❌ No real-time import happens

It is NOT:

* webhook-based
* event-based
* live sync

It is:
👉 scheduled batch import

---

# 5. How to test quickly

## Step 1: Add rows in Google Sheet

---

## Step 2: Run cron

```
drush cron
```

---

## Step 3: Run queue

```
drush queue-run my_custom_module_import_queue
```

---

## Step 4: Check logs

```
drush watchdog:show grocery_import
```

or:

```
/admin/reports/dblog
```

---

# 6. If you want “auto import when rows change”

Right now your system is **batch-based**, but you can upgrade it:

## Option A (recommended)

Add **Google Sheets webhook (Apps Script)** → call Drupal endpoint

## Option B

Run cron every X minutes:

```
/admin/config/system/cron
```

## Option C

Use external scheduler:

* Linux cron job
* GitHub Actions
* Cloud Scheduler (GCP)

---

# 7. Simple summary

* Cron → queues stores
* Queue → imports data
* Manual run → `drush queue-run`
* Not real-time import





## 2. Data source (Google Sheets)

The module builds a CSV URL from store fields:

* `field_google_sheet_url`
* `field_google_sheet_tab_gid`

Example:

```
https://docs.google.com/spreadsheets/d/.../export?format=csv&gid=123456
```

---

## 3. Required CSV columns

Minimum required fields:

```
product_id
product_name
variation_sku
```

---

### Example additional fields

```
variant_name
price
currency
quantity
unit
pack_type
stock
brand
category
sub_category
image_url
status
```

---

## 4. Cron and queue system

### Cron entry point

```php
function my_custom_module_cron() {
  App::instance()->hookCron();
}
```

---

### What cron does

* Loads all Commerce stores
* Adds each store ID to a queue
* Does NOT process immediately

---

### Queue processing

Each queue item:

* Calls import for a store
* Prevents timeouts for large data

---

## 5. Import flow

Main function:

```php
my_custom_module_import($store_id)
```

---

### Steps:

1. Load store
2. Build Google Sheet URL
3. Fetch CSV
4. Parse CSV
5. Process rows

---

## 6. CSV handling

### Fetch

* Uses `file_get_contents` with timeout
* Captures HTTP errors
* Validates non-empty response

---

### Parse

* Normalizes line breaks
* Reads header row
* Validates required columns
* Skips malformed rows

---

### Validation

Each row must have:

* product_id
* product_name
* variation_sku

Invalid rows are skipped with log warnings.

---

## 7. Product handling

### Load or create

Products are identified by:

* `field_product_id`
* Store ID
* Product type: `grocery_product`

---

### Update fields

* Title
* Status
* Description
* Brand (taxonomy)
* Category (taxonomy)
* Sub-category (taxonomy)

---

## 8. Variation handling

### Load or create

Variations are identified by:

* SKU (`variation_sku`)

---

### Update fields

* Title
* Price
* Currency
* Quantity / unit
* Stock
* Expiry
* Origin
* Status

---

## 9. Product ↔ Variation linking

* Ensures variation is attached only once
* Avoids duplicates

---

## 10. Image handling

Function:

```php
my_grocery_download_image()
```

---

### Behavior:

* Downloads image via HTTP client
* Saves to `public://`
* Replaces existing file if needed

---

### Fallback:

If image fails:

* Logs error
* Uses placeholder image

---

## 11. Taxonomy handling

Function:

```
get_or_create_term()
```

---

### Behavior:

* Loads term by name
* Creates if not found
* Works for:

  * brand
  * category
  * sub_category

---

## 12. Error handling

### Try/catch used in:

* Import process
* CSV fetch
* Image download

---

### Logging channels:

* `grocery_import`
* `my_custom_module`

---

### Example logs:

* Import started / completed
* Missing fields
* Invalid rows
* Image failures

---

## 13. Store configuration

Each Commerce store must have:

* Google Sheet URL
* Sheet GID

---

## 14. Performance considerations

* Uses queue to avoid long cron execution
* Processes store-by-store
* Skips invalid rows instead of failing full import

---

## 15. How to run manually

You can trigger import:

```
my_custom_module_import($store_id);
```

---

## 16. Common issues

### Empty CSV

* Check Google Sheet sharing (must be public or accessible)

---

### Invalid URL

* Ensure correct export format (`csv`)

---

### Missing fields

* Ensure required columns exist in header

---

### Images not loading

* Check image URLs
* Check server outbound HTTP access

---

### Logging

Logs available for:

* Import status
* Errors
* Skipped rows
* SKU processing

## Notes

* Each store uses its own Google Sheet
* Ensure sheet is **published to web with CSV format


1. Images not found issue error messages are logged properly.
  Line x of csv for store xx references image https://via.placeholder.com/300 which is not a valid image”
2. If product imported with product name cumin and renamed product cumin to cuminx reimport again then cuminx is updated. 
3. 2 products with variant one is published and one is unblished then single product created and created 2 variants created
  with one variant unpublished and one unpublished respectively.
4. Empty lines are skipped not throwing errors.
