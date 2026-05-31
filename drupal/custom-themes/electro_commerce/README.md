# Electro Commerce — Drupal 11 Commerce Bootstrap 5 Subtheme

A production-ready Drupal 11 Commerce subtheme that recreates the **Electro Electronics** HTML template using Drupal standards, Bootstrap 5, and Drupal Commerce.

---

## Requirements

| Dependency | Version |
|---|---|
| Drupal | ^10 \|\| ^11 |
| Bootstrap5 (base theme) | ^4 |
| Drupal Commerce | ^3 |
| Views | Core |
| Paragraphs | ^1.15 |
| Layout Builder *(optional)* | Core |

---

## Installation

1. **Install the base theme first:**
   ```
   composer require drupal/bootstrap5
   drush en bootstrap5
   ```

2. **Copy this theme to your Drupal themes directory:**
   ```
   cp -r electro_commerce /path/to/drupal/web/themes/custom/
   ```

3. **Enable and set as default:**
   ```
   drush en electro_commerce
   drush config-set system.theme default electro_commerce
   drush cr
   ```

---

## Content Types & Fields Needed

### 1. Product (via Drupal Commerce)

Install Commerce and create a product type called **"Electronics"** with these variation fields:

| Field | Type | Notes |
|---|---|---|
| `field_images` | Image | Multiple, for product gallery |
| `field_badge` | List (text) | Values: `new`, `sale` |
| `field_category` | Entity Reference | Taxonomy: `product_category` |
| `field_rating` | Number (integer) | 1–5 |

Variation fields:
| Field | Type |
|---|---|
| `price` | Commerce Price |
| `field_compare_price` | Commerce Price (original/compare-at) |

### 2. Paragraph Types

Create these paragraph types (admin > Structure > Paragraph Types):

#### `hero_slide`
| Field | Type |
|---|---|
| `field_slide_image` | Image |
| `field_slide_eyebrow` | Text (plain) |
| `field_slide_title` | Text (plain) |
| `field_slide_subtext` | Text (plain) |
| `field_slide_cta_url` | Link |
| `field_slide_cta_text` | Text (plain) |

#### `product_offer_banner`
| Field | Type |
|---|---|
| `field_offer_eyebrow` | Text (plain) |
| `field_offer_title` | Text (plain) |
| `field_offer_discount` | Text (plain) |
| `field_offer_image` | Image |
| `field_offer_url` | Link |

### 3. Basic Page (Home)

Add a field `field_hero_slides` (Paragraphs, `hero_slide` type) to the Basic Page content type for the homepage slider.

---

## Block Configuration (Drupal Admin > Structure > Block Layout)

Map blocks to these theme regions:

| Region | Block |
|---|---|
| `topbar_left` | Custom Block: "Topbar Links" |
| `topbar_center` | Custom Block: "Topbar Phone" |
| `topbar_right` | Custom Block: "Topbar Dashboard" |
| `header_logo` | Site Branding block |
| `header_search` | Search Form block |
| `header_actions` | Custom Block: "Header Cart Actions" |
| `primary_menu` | Main Navigation menu block |
| `hero_slider` | Custom Block: "Hero Slider" (paragraphs) — front page only |
| `hero_banner` | Custom Block: "Hero Side Banner" — front page only |
| `services_bar` | Custom Block: "Services Bar" — front page only |
| `product_offers` | Views block: "Product Offers" — front page only |
| `products_tabs` | Views block: "Our Products" — front page only |
| `product_banners` | Custom Block: "Product Banners" — front page only |
| `product_list` | Views block: "Product List" — front page only |
| `bestsellers` | Views block: "Bestseller Products" — front page only |
| `footer_contact` | Custom Block: "Footer Contact Info" |
| `footer_newsletter` | Custom Block: "Footer Newsletter" |
| `footer_customer_service` | Menu block: "Footer Customer Service" |
| `footer_information` | Menu block: "Footer Information" |
| `footer_extras` | Menu block: "Footer Extras" |

---

## Views Configuration

### View: "Our Products" (`our_products`)
- **Machine name:** `our_products`
- **Display:** Block
- **Format:** Unformatted list → use `views-view-unformatted--product-grid.html.twig`
- **Fields:** Rendered entity (product, teaser view mode)
- **Filters:** Published = Yes
- **Sort:** Created DESC
- **Items per page:** 8
- **Attachments:** Create 3 more block displays filtered by:
  - "New Arrivals" → `field_badge = new`, 4 items
  - "Featured" → `field_featured = 1`, 4 items
  - "Top Selling" → sort by `field_sales_count` DESC, 4 items

### View: "Bestseller Products" (`bestseller_products`)
- **Machine name:** `bestseller_products`
- **Display:** Block
- **Format:** Unformatted list → use `views-view-unformatted--bestsellers.html.twig`
- **Fields:** Rendered entity (product, **mini** view mode)
- **Filters:** `field_badge = featured` OR sort by purchases
- **Items per page:** 6

### View: "Product List" (`product_list`)
- **Machine name:** `product_list`
- **Display:** Block
- **Format:** Unformatted list
- **Fields:** Rendered entity (product, mini view mode)
- **Items per page:** 8

---

## Commerce Product View Modes

Go to **Admin > Commerce > Configuration > Product types > [your type] > Manage display**:

| View Mode | Template Used | Where |
|---|---|---|
| `teaser` | `commerce-product.html.twig` | Product card grid |
| `mini` | `commerce-product--mini.html.twig` | Bestsellers / product list |
| `full` | Drupal default | Single product page |

---

## Menus to Create

Go to **Admin > Structure > Menus > Add Menu:**

1. **Main navigation** (already exists) — primary navbar
2. **Footer Customer Service** (`footer-customer-service`)
3. **Footer Information** (`footer-information`)
4. **Footer Extras** (`footer-extras`)

---

## Taxonomy

Create a vocabulary **"Product Category"** (machine name: `product_category`) with terms:
- Accessories
- Electronics & Computer
- Laptops & Desktops
- Mobiles & Tablets
- SmartPhone & Smart TV

---

## Hero Slider Implementation

The hero slider uses Bootstrap 5's native Carousel component.

In your homepage node (or via Layout Builder), add a Paragraph field `field_hero_slides` with `hero_slide` paragraph items.

In your custom block template or node template, output the slider like this:

```twig
<div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-inner">
    {% for delta, item in content.field_hero_slides['#items'] %}
      <div class="carousel-item {% if delta == 0 %}active{% endif %}">
        {{ content.field_hero_slides[delta] }}
      </div>
    {% endfor %}
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon"></span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon"></span>
  </button>
</div>
```

---

## CSS Variables / Theming

Override any design token in `css/electro-commerce.css`:

```css
:root {
  --ec-primary:   #0d6efd;  /* Main blue */
  --ec-secondary: #f28b00;  /* Orange accent */
  --ec-dark:      #1a1a2e;  /* Footer/dark bg */
}
```

---

## File Structure

```
electro_commerce/
├── electro_commerce.info.yml       # Theme declaration
├── electro_commerce.libraries.yml  # CSS/JS library definitions
├── electro_commerce.theme          # PHP preprocess functions
├── css/
│   ├── electro-commerce.css        # Main stylesheet
│   └── electro-editor.css          # CKEditor styles
├── js/
│   └── electro-commerce.js         # Theme behaviors
├── images/
│   └── product-placeholder.png     # Default product image
├── templates/
│   ├── page/
│   │   ├── html.html.twig
│   │   └── page.html.twig          # Main page layout
│   ├── block/
│   │   └── block--services-bar.html.twig
│   ├── commerce/
│   │   ├── commerce-product.html.twig          # Product card
│   │   └── commerce-product--mini.html.twig    # Product mini card
│   ├── views/
│   │   ├── views-view--our-products.html.twig
│   │   ├── views-view-unformatted--product-grid.html.twig
│   │   └── views-view-unformatted--bestsellers.html.twig
│   └── paragraph/
│       ├── paragraph--hero-slide.html.twig
│       └── paragraph--product-offer-banner.html.twig
└── config/
    └── install/
        └── block.block.electro_commerce_hero_slider.yml
```

---

## Drush Commands (Quick Setup)

```bash
# Enable required modules
drush en commerce commerce_product commerce_order commerce_cart commerce_checkout paragraphs views views_ui

# Clear caches after theme changes
drush cr

# Import optional config
drush config-import --partial --source=themes/custom/electro_commerce/config/install
```
