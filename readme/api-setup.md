
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
→ /api/store/grocery_store

GET products:
→ /api/product

GET single product:
→ /api/product/default/{uuid}

GET products + variations:
→ ?include=variations

GET products by store:
→ ?filter[stores.id]=STORE_UUID



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
