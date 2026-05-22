
# Api Setup
---

Ensure public and private keys are generated at drupal/starter-data/private-files/social-auth-oauth-keys/

# Generate private key
openssl genrsa -out private.key 2048

# Generate public key
openssl rsa -in private.key -pubout -out privapublic.key


### 1.1 Enabled JSON:API for required entities

Go to:
Admin → Configuration → Web services → JSON:API

enabled resources:
commerce_product
commerce_product_variation
commerce_store


### 1.2 Clean endpoints using JSON:API Extras

Go to:
Admin → Configuration → Web services → JSON:API Extras

A. Rename resource types

Example mapping:

Entity	Default	Clean
Product	commerce_product--default	products
Variation	commerce_product_variation--default	variations
Store	commerce_store--online	stores
Edit each resource → Resource Type Name

B. Disable unnecessary fields

Inside each resource:
Disable:
uid
revision_id
created
internal fields not needed by frontend
Keep only:
title
price
images
SKU
relationships

C. Example public endpoints

GET stores:
→ /api/store/online

GET grocery products:
→ /api/product/grocery

GET single grocery product:
→ /api/product/grocery/{uuid}

ex:- 
anonymous users  can see stores and products.

1. list of stores 

GET  https://lakaybeta.dcycleproject.org/api/store/online

2. grocery products 

GET https://lakaybeta.dcycleproject.org/api/product/grocery 

3. GET single grocery products
GET /api/product/grocery/{uuid}

ex:-  search by type commerce_product--grocery_product, you will get id of a product.

```
"data":{"type":"commerce_product--grocery_product","id":"22ceba2e-97c3-4039-bf41-54b640e6dd27"....
```

- If you want to access specific field.

/< api path >?fields[target entity type]=field1,field2

ex:-

https://lakaybeta.dcycleproject.org/api/store/online?fields[commerce_store--online]=name,address

Access name and address of a fields.


- unpublished grocery product
/api/product/grocery?filter[status]=0


- Include relationships:
GET /en/api/commerce_product/default?include=variations,stores

### Product Variations API

List variations
GET /api/product_variation/grocery_variation

Single variation
GET /api/product_variation/grocery_variation/{uuid}


### Get published product and variant and get variations and images fields.

```
https://<domain name>/api/product/grocery?filter[status][value]=1&filter[variations.status][value]=1&include=variations,field_image,variations.field_image

```

we can also add filters, sort by, include options in json api resources (/admin/config/services/jsonapi/resource_types).


### Get customers 
/api/profile/customer	


### Taxonomy

GET /api/taxonomy_term/brand
GET /api/taxonomy_term/category	
GET /api/taxonomy_term/sub_category	

### Grocery Variations
GET /api/commerce_product_variation/grocery_variation
GET /api/commerce_product_variation/grocery_variation/{uuid}

### Users
http://localhost:61448/api/user/user

### roles:

/admin/people/roles


- If you want to access multilingual content then add /<language code>/ to api host.

for example:- Get french default stores.

1. list of stores 

GET  https://lakaybeta.dcycleproject.org/fr/api/store/online


### 1.3 Allow anonymous read-only access

Go to:

Admin → People → Permissions

Grant to Anonymous:
Access JSON:API resource list
Access JSON:API resource by ID

BUT restrict via entity permissions:

Go to:
Admin → Commerce → Products → Product types → Manage permissions

Ensure:
Anonymous → View published products

NOT:
Create
Update
Delete


### 1.4 Example: Fetch products (anonymous)

curl https://example.com/api/products

With includes:
curl "https://example.com/api/products?include=variations,variations.field_image"


## 2) User Authentication API

Simple OAuth (API authentication)

### 2.1 Configured Simple OAuth

Go to:
Admin → Configuration → People → Simple OAuth

Create Client
Client ID: frontend_app
Client Secret: (auto generated)

Grant Types:
- Authorization Code
- Refresh Token

Redirect URI:
https://frontend-app.com/callback


### 2.2 Token Flow

### Step 1: Google Login

Frontend:
Redirect user to Google via OpenID Connect
Drupal:
Authenticates user
Creates/loads Drupal user (buyer role)

### Step 2: Exchange session → OAuth token

Use:

/oauth/authorize
/oauth/token

Authorization Code Flow
Request authorization code:

GET /oauth/authorize?
 response_type=code
 &client_id=frontend_app
 &redirect_uri=https://frontend-app.com/callback
Exchange for token:
curl -X POST https://example.com/oauth/token \
  -d "grant_type=authorization_code" \
  -d "client_id=frontend_app" \
  -d "client_secret=SECRET" \
  -d "code=AUTH_CODE" \
  -d "redirect_uri=https://frontend-app.com/callback"
Response:
{
  "access_token": "abc123",
  "refresh_token": "xyz456",
  "expires_in": 3600,
  "token_type": "Bearer"
}

### 2.3 Authenticated API Requests
Frontend must send:
Authorization: Bearer ACCESS_TOKEN

### 2.4 Restrict API access by role

Use:
OAuth scopes
Drupal permissions



# Authentication Flow

## Step 1: Create OAuth Consumer

Create a consumer from Drupal Admin:

```text
/admin/config/services/consumer
```

Create Consumer:

| Field           | Value                   |
| --------------- | ------------------------|
| Label           | Mobile App / Frontend   |
| Client ID       | auto-generated          |
| Client Secret   | auto-generated          |
| Grant types     | Authorization Code,     |
|                 | Refresh Token           |
| Is Confidential | TRUE                    |
| Scopes          | Automatic authorization,|
                  |  Use PKCE?              | 


Access token expiry : 3600
refresh token : 2592000

Redirect URIs : https://frontend.example.com/auth/callback


Save the consumer and copy:

* client_id
* client_secret

---

# Anonymous APIs

Anonymous users can:

* View stores
* View products
* View product variations

No authentication required.

---

# Get Stores

## Endpoint

```
GET /api/store/online
```

## Example

```
curl --location --request GET '${BASE_URL}/api/store/online'
```

## Response

```
{
  "data": [
    {
      "type": "commerce_store--online",
      "id": "store-uuid",
      "attributes": {
        "name": "Main Grocery Store"
      }
    }
  ]
}
```

---

# Get Grocery Products

## Endpoint

```
GET /api/product/grocery
```

## Example

```
curl --location --request GET '${BASE_URL}/api/product/grocery'
```

---

# Get Products With Variations

## Endpoint

```
GET /api/product/grocery?include=variations
```

## Example

```
curl --location --request GET '${BASE_URL}/api/product/grocery?include=variations'
```

---

# Get Single Product

## Endpoint

```
GET /api/product/grocery/{product_uuid}?include=variations
```

## Example

```
curl --location --request GET '${BASE_URL}/api/product/grocery/PRODUCT_UUID?include=variations'
```

---

# Get Product Variations

## Endpoint

```
GET /api/product_variation/grocery_variation
```

## Example

```
curl --location --request GET '${BASE_URL}/api/product_variation/grocery_variation'
```

## Sample Response

```
{
  "data": [
    {
      "type": "commerce_product_variation--grocery_product_variation",
      "id": "variation-uuid",
      "attributes": {
        "title": "Apple 1kg",
        "sku": "APL-1KG",
        "price": {
          "number": "120.00",
          "currency_code": "INR"
        }
      }
    }
  ]
}
```

---

# Buyer Authentication (Google / Gmail Login)

Frontend or mobile app flow:

```text
Google Login
    ↓
Get Gmail Email
    ↓
Create/Login Drupal User
    ↓
Generate OAuth Access Token
```

---

# Generate Access Token

## Endpoint

```
POST /oauth/token
```

## Headers

```
Content-Type: application/json
```

## Payload

```
{
  "grant_type": "password",
  "client_id": "CLIENT_ID",
  "client_secret": "CLIENT_SECRET",
  "username": "buyer@gmail.com",
  "password": "USER_PASSWORD"
}
```

## Example

```
curl --location --request POST '${BASE_URL}/oauth/token' \
--header 'Content-Type: application/json' \
--data '{
  "grant_type": "password",
  "client_id": "CLIENT_ID",
  "client_secret": "CLIENT_SECRET",
  "username": "buyer@gmail.com",
  "password": "USER_PASSWORD"
}'
```

## Response

```
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "ACCESS_TOKEN",
  "refresh_token": "REFRESH_TOKEN"
}
```

---

# Authenticated Request Headers

Use access token for all authenticated APIs.

```
Authorization: Bearer ACCESS_TOKEN
Accept: application/vnd.api+json
Content-Type: application/vnd.api+json
```

---

# Add Product To Cart

Drupal Commerce automatically creates cart when adding first item.

## Endpoint

```
POST /api/cart/add
```

## Headers

```
Authorization: Bearer ACCESS_TOKEN
Content-Type: application/json
```

## Payload

```
{
  "data": {
    "type": "cart-item",
    "attributes": {
      "quantity": 2
    },
    "relationships": {
      "purchased_entity": {
        "data": {
          "type": "commerce_product_variation--grocery_product_variation",
          "id": "VARIATION_UUID"
        }
      }
    }
  }
}
```

## Example

```
curl --location --request POST '${BASE_URL}/api/cart/add' \
--header 'Authorization: Bearer ACCESS_TOKEN' \
--header 'Content-Type: application/json' \
--data '{
  "data": {
    "type": "cart-item",
    "attributes": {
      "quantity": 2
    },
    "relationships": {
      "purchased_entity": {
        "data": {
          "type": "commerce_product_variation--grocery_product_variation",
          "id": "VARIATION_UUID"
        }
      }
    }
  }
}'
```

---

# View Cart

## Endpoint

```
GET /api/commerce_order/default?filter[state][value]=draft
```

## Example

```
curl --location --request GET '${BASE_URL}/api/commerce_order/default?filter[state][value]=draft' \
--header 'Authorization: Bearer ACCESS_TOKEN'
```

---

# Get Cart Items

## Endpoint

```
GET /api/commerce_order_item/default
```

## Example

```
curl --location --request GET '${BASE_URL}/api/commerce_order_item/default' \
--header 'Authorization: Bearer ACCESS_TOKEN'
```

---

# Update Cart Item Quantity

## Endpoint

```
PATCH /api/commerce_order_item/default/{order_item_uuid}
```

## Payload

```
{
  "data": {
    "type": "commerce_order_item--default",
    "id": "ORDER_ITEM_UUID",
    "attributes": {
      "quantity": "5"
    }
  }
}
```

## Example

```
curl --location --request PATCH '${BASE_URL}/api/commerce_order_item/default/ORDER_ITEM_UUID' \
--header 'Authorization: Bearer ACCESS_TOKEN' \
--header 'Content-Type: application/vnd.api+json' \
--data '{
  "data": {
    "type": "commerce_order_item--default",
    "id": "ORDER_ITEM_UUID",
    "attributes": {
      "quantity": "5"
    }
  }
}'
```

---

# Remove Cart Item

## Endpoint

```
DELETE /api/commerce_order_item/default/{order_item_uuid}
```

## Example

```
curl --location --request DELETE '${BASE_URL}/api/commerce_order_item/default/ORDER_ITEM_UUID' \
--header 'Authorization: Bearer ACCESS_TOKEN'
```

---

# Attach Billing Information

## Endpoint

```
PATCH /api/commerce_order/default/{order_uuid}
```

## Payload

```
{
  "data": {
    "type": "commerce_order--default",
    "id": "ORDER_UUID",
    "attributes": {
      "mail": "buyer@gmail.com"
    }
  }
}
```

---

# Checkout Flow

Typical checkout steps:

```text
Add Products To Cart
    ↓
View Cart
    ↓
Update Quantities
    ↓
Attach Billing Details
    ↓
Create Stripe Payment
    ↓
Complete Order
    ↓
View Orders
```

---

# Stripe Payment

Depending on Stripe integration configuration.

## Endpoint

```
POST /api/commerce_payment/default
```

## Payload

```
{
  "data": {
    "type": "commerce_payment--default",
    "attributes": {
      "payment_gateway": "stripe",
      "amount": {
        "number": "240.00",
        "currency_code": "INR"
      }
    },
    "relationships": {
      "order_id": {
        "data": {
          "type": "commerce_order--default",
          "id": "ORDER_UUID"
        }
      }
    }
  }
}
```

---

# Complete Order

## Endpoint

```
PATCH /api/commerce_order/default/{order_uuid}
```

## Payload

```
{
  "data": {
    "type": "commerce_order--default",
    "id": "ORDER_UUID",
    "attributes": {
      "state": "completed"
    }
  }
}
```

## Example

```
curl --location --request PATCH '${BASE_URL}/api/commerce_order/default/ORDER_UUID' \
--header 'Authorization: Bearer ACCESS_TOKEN' \
--header 'Content-Type: application/vnd.api+json' \
--data '{
  "data": {
    "type": "commerce_order--default",
    "id": "ORDER_UUID",
    "attributes": {
      "state": "completed"
    }
  }
}'
```

---

# View My Orders

## Endpoint

```
GET /api/commerce_order/default
```

## Example

```
curl --location --request GET '${BASE_URL}/api/commerce_order/default' \
--header 'Authorization: Bearer ACCESS_TOKEN'
```

---

# Get Single Order

## Endpoint

```
GET /api/commerce_order/default/{order_uuid}
```

## Example

```
curl --location --request GET '${BASE_URL}/api/commerce_order/default/ORDER_UUID' \
--header 'Authorization: Bearer ACCESS_TOKEN'
```

---

# API Flow Summary

## Anonymous User Flow

```text
Get Stores
    ↓
Get Products
    ↓
Get Product Variations
```

---

## Authenticated Buyer Flow

```text
Google Login
    ↓
Generate OAuth Token
    ↓
Add Product To Cart
    ↓
View Cart
    ↓
Update Cart
    ↓
Stripe Payment
    ↓
Complete Order
    ↓
View Orders
```


# We can add cors to drupal/settings/services.yml

```
 cors.config:
   enabled: true
   allowedHeaders: ['*']
   allowedMethods: ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']
   allowedOrigins: ['https://frontend.example.com']
   exposedHeaders: false
   maxAge: 1000
   supportsCredentials: true
```
