**Google social login in Drupal** using **drupal Social Auth google module** and **Google Cloud**.

---

# 0. Prepare Google Cloud account and project

Go to:
👉 [https://console.cloud.google.com/](https://console.cloud.google.com/)

This opens Google Cloud Console

---

## Step 0.1 — Sign in

* Log in with your Google account
* Accept terms if prompted
* If it’s your first time, Google may ask for:

  * Country
  * Agreement acceptance
  * Billing setup (not required for basic OAuth use, but sometimes prompted)

---

## Step 0.2 — Create a project

Direct link:
👉 [https://console.cloud.google.com/projectcreate](https://console.cloud.google.com/projectcreate)

Screen:

* **Project name** → e.g. `Drupal Google Login`
* (Optional) Organization

Click **Create**
After a few seconds, select that project.

---

## Step 0.3 — Select project

Direct link:
👉 [https://console.cloud.google.com/projectselector2/home/dashboard](https://console.cloud.google.com/projectselector2/home/dashboard)

* Choose your newly created project
* Make sure it shows in the top bar

---

# 1. Configure OAuth consent screen

### Steps:

1. Create or select a project
2. Go to **APIs & Services → Credentials**
3. Click **Create Credentials → OAuth Client ID**
4. If prompted, configure **OAuth consent screen**

   * App name → your site name
   * User support email → your email

   * Authorized domain (your site domain)

   **User Type**

    * External → for public websites

    * Developer contact - Add your email

       Click **Save and Continue**

    * Scopes - You can skip for now

    Click **Save and Continue**


### Test users (important if in testing mode)

* Add your email

Click **Save and Continue → Back to dashboard**


### Create Client:
* click on client
* click on **create client**
* Application type: **Web application**
* Add **Authorized redirect URIs**

Example:

```
https://yourdomain.com/user/login/google/callback
```

* Authorized JavaScript origins (not required right now)
For use with requests from a browser
```
https://yourdomain.com/
```

⚠️ Must match Drupal exactly.


After saving, you’ll get:

* Client ID
* Client Secret

note down Client ID and Client Secret.

# 2. Drupal setup

Install module:

* Social Auth Google (module installed and enabled in lakaylink)

---

## Install via Composer

```
composer require drupal/social_auth_google
```

Enable:

```
drush en social_auth social_auth_google
```

---

## Configure in Drupal

```
https://yourdomain.com/admin/config/social-api/social-auth/google
```

---

### fields:

* Client ID → paste from Google
* Client Secret → paste
* Scopes:

```
openid,email,profile
```

* Verify Authorized redirect URL (important)

Drupal callback:

```
https://yourdomain.com/user/login/google/callback
```

Full URL must match what you added in Google Cloud.

* Social Auth Settings

  Post login path
  - Enter post login user redirect url (/user)


   What can users do?
    select Register and login

- configure below option as per your requirement.
    * Redirect new users to Drupal user form

    * Disable Social Auth login for administrator

- Disable Social Auth login for the following roles
  select Content editor, Administrator


Save configuration

---
# 3. Account settings (/admin/config/people/accounts )

Who can register accounts? select visitors

---
# 4. Add social auth block to the region.

add social auth block to whichever region you preferred in admin/structure/block page.

Currently Enabled only for anonymous user.

---

# 5. Test login

Open:

```
https://yourdomain.com/
```

You should see:

* “Login with Google”
* onclick you should see google login page.
* Upon successfull login you should see the /user page

---

# Common mistakes (quick check)

* Redirect URI mismatch
* Wrong project selected in Google Cloud
* Consent screen not configured
* HTTP instead of HTTPS

---

If you want, I can add screenshots-style annotations (what each screen looks like) or show how to restrict login to a company domain.
