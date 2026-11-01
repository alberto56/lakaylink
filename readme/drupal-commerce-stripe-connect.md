# Drupal Commerce + Stripe Integration Guide

---

# 1. Stripe Account Setup

## 1.1 Create Stripe Account

Go to:  
https://dashboard.stripe.com/register  

Provide:
- Email
- Password
- Country
- Business details

---

## 1.2 Activate Account (Required for Live Mode)

Navigate to:  
https://dashboard.stripe.com/settings/account  

Complete:
- Business information
- Bank account details
- Identity verification (for live payments)

> ⚠️ Test mode does NOT require full activation.

---

# 2. Sandbox (Test Mode) Setup

Stripe uses **Test Mode** instead of a traditional "sandbox".

## Enable Test Mode

In Stripe Dashboard:
- Toggle **“Test mode”** (top-right corner)

You will now see:
- Test API keys
- Test transactions
- No real charges applied

---

# 3. Stripe API Keys

## 3.1 Get API Keys

Go to:  
https://dashboard.stripe.com/apikeys  

You will see:

| Key Type | Example | Usage |
|----------|--------|------|
| Publishable Key | `pk_test_...` | Frontend |
| Secret Key | `sk_test_...` | Backend |


note down publishable and Secret keys

---

## 3.2 Key Types

| Mode | Keys |
|------|------|
| Test Mode | `pk_test`, `sk_test` |
| Live Mode | `pk_live`, `sk_live` |

> ⚠️ Never mix test and live keys.

---

# 4. Install Drupal Commerce Stripe

## Install via Composer

```
composer require drupal/commerce_stripe
```

Enable Modules
drush en commerce commerce_payment commerce_stripe -y

# 5. Configure Stripe Payment Gateway in Drupal

##  5.1 Navigation Path

/admin/commerce/config/payment-gateways
Click:
Add payment gateway

##  5.2 Select Plugin

Field	Value
Name as < you prefer >
Plugin	Stripe Payment Element



##  5.3 Gateway Configuration

General Settings

Field	Example

- Display name (Shown to customers during checkout) : < as you prefer >
- Mode :  Test
- Collect billing information :  enabled
- Authentication Method - API keys
  Enter Publishable key
  Secret key
- Express Checkout configuration - disabled
- Webhooks (we will enabled it in next step)
- Payment method usage : On-session: the customer will always initiate payments in checkout
- Capture method : Automatic Async: Stripe automatically captures funds when the customer authorizes the payment. Recommended over "automatic" due to improved latency.

save 

# 6. Drupal Stripe Webhook URL

##  6.1 Get Webhook URL

After saving gateway:

Edit payment gateway you have have added.

/admin/commerce/config/payment-gateways/{gateway}

you will see webhook URL at Webhook endpoint URL.

Example webhook URL:
https://yourdomain.com/payment/notify/{gateway}

copy webhook url.

# 7. Stripe Webhook Setup

##  7.1 Open Stripe Webhooks

  https://dashboard.stripe.com/webhooks
  Click:
  Add destination

## 7.2 Configure Endpoint

  Endpoint URL : paste webhook url copied from drupal your payment gateway page.


## 7.3 Select Events

  Enable:
  payment_intent.succeeded
  payment_intent.payment_failed
  checkout.session.completed
  charge.refunded
  charge.dispute.created
  Click:
  Add events

## 7.4 Create Webhook
Click:
Add endpoint

# 8. Webhook Signing Secret

After creation:
You will see:
whsec_XXXXXXXXXXXXXXXX
Click Reveal and copy it.

# 9. Configure Webhook in Drupal

Navigation Path
/admin/commerce/config/payment-gateways/{gateway}/edit
Field: Webhook Signing Secret
Paste:
whsec_...
Click Save


# 11. Testing Payments

Drupal Checkout URL
/checkout/{order_id}

Test Card
4242 4242 4242 4242

Field	Value

Expiry	Any future date

CVC	Any 3 digits

# 12. Webhook Verification

  Stripe Dashboard:

  Developers → Webhooks → Endpoint

  Status	Meaning

  200	Success

  Failed	Error in Drupal or signature mismatch

# 13. Production (Live Mode)
  Steps

  Disable test mode in Stripe

  Copy live keys:
  pk_live_...
  sk_live_...

  Update Drupal gateway

  Create new live webhook endpoint

  Add live signing secret

# 14. Security Best Practices

  Never expose secret keys in frontend

  Store keys in environment variables or settings.php

  Use HTTPS only

  Rotate API keys periodically

  Validate webhook signatures

# 17. Moving to Production (Live Mode) 🚀

This section explains how to safely move your Drupal Commerce + Stripe integration from **test (sandbox)** to **production (live mode)**.

---

# 17.1 Pre-Production Checklist

Before switching to live mode, ensure:

- [ ] Test payments are working in Drupal
- [ ] Webhook events are successfully received (200 OK in Stripe)
- [ ] Orders are correctly marked as “Paid”
- [ ] Email notifications are working
- [ ] Refund flow (if enabled) is tested
- [ ] HTTPS is enabled on your domain

---

# 17.2 Switch Stripe to Live Mode

## Step 1: Enable Live Mode in Stripe

Go to:
https://dashboard.stripe.com

Toggle:
- ❌ Test Mode OFF  
- ✅ Live Mode ON  

---

## Step 2: Get Live API Keys

Navigate to:
https://dashboard.stripe.com/apikeys

Copy:

| Key Type | Example |
|----------|--------|
| Publishable Key | `pk_live_...` |
| Secret Key | `sk_live_...` |

---

# 17.3 Update Drupal Payment Gateway

## Step 1: Edit Gateway

Go to: /admin/commerce/config/payment-gateways


Click your Stripe gateway → **Edit**

---

## Step 2: Update Mode

| Field | Value |
|------|------|
| Mode | Live |

---

## Step 3: Replace API Keys

Update:

| Field | Value |
|------|------|
| Publishable Key | `pk_live_...` |
| Secret Key | `sk_live_...` |

Click **Save**

---

# 17.4 Create Production Webhook (Important)

⚠️ You MUST create a separate webhook for live mode.

---

## Step 1: Go to Stripe Webhooks

https://dashboard.stripe.com/webhooks

Click:
👉 Add endpoint

---

## Step 2: Add Live Webhook URL

Use your production Drupal endpoint: https://yourdomain.com/payment/stripe/webhook


---

## Step 3: Select Events

Enable:

- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `checkout.session.completed`
- `charge.refunded`
- `charge.dispute.created`

Click **Add events**

---

## Step 4: Copy Signing Secret

After creation: whsec_XXXXXXXXXXXXXXXX


Copy it.

---

## Step 5: Update Drupal Webhook Secret

Go to:

/admin/commerce/config/payment-gateways/{gateway}/edit

paste:
whsec_...


Save configuration.

---

# 17.5 Verify Production Setup

## Step 1: Test Live Payment (Small Amount)

Use real card (small transaction recommended).

Check:
- Payment succeeds in Stripe
- Order is marked paid in Drupal
- Webhook logs show 200 OK

---

## Step 2: Stripe Dashboard Verification

Go to:

- Payments → confirm transaction appears
- Webhooks → check delivery status

---

# 17.6 Go-Live Safety Checklist

Before announcing production launch:

- [ ] Live API keys configured
- [ ] Live webhook created
- [ ] Webhook signing secret added
- [ ] HTTPS enabled
- [ ] Order emails working
- [ ] Refund process tested
- [ ] Admin alerts/logging enabled

---

# 17.7 Recommended Production Settings

## Enable Logging (Drupal)

- Enable Commerce Stripe logging
- Monitor watchdog logs
- Track failed payments

---

## Enable Backup Payment Monitoring

Optional but recommended:

- Stripe email alerts for failed payments
- Webhook retry enabled in Stripe
- Drupal queue worker enabled for webhook processing

---

# 17.8 Common Production Mistakes ❌

### ❌ Using test keys in live mode
→ Payments will fail silently

### ❌ Missing live webhook
→ Orders not updated

### ❌ Wrong webhook URL
→ Stripe will show failed deliveries

### ❌ No HTTPS
→ Stripe may reject requests

---

# 17.9 Final Production Flow

1. Switch Stripe to Live Mode  
2. Replace API keys in Drupal  
3. Create live webhook endpoint  
4. Add signing secret  
5. Test real payment  
6. Confirm order lifecycle  
7. Monitor Stripe dashboard  
