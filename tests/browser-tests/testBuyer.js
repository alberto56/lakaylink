const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')

it('buyer should see the store he has joined.', async function() {
  this.timeout(25000);
  const puppeteer = require('puppeteer')
  const browser = await puppeteer.launch({
     headless: true,
     args: ['--no-sandbox', '--disable-setuid-sandbox']
  })
  // var result = false
  const page = await browser.newPage()
  try {
    await page.setViewport({
      width: 1280,
      height: 800
    });

    const userPassword = process.env.DRUPALPASS;

    await page.goto(
      'http://webserver/my-custom-module/test-user-login',
      {
        waitUntil: 'networkidle2'
      }
    );

    await testBase.screenshot(
      page,
      'buyer-user-test-login',
      await page.content()
    );

    // Fill username and password.
    await page.type('input[name="name"]', 'test_buyer');
    await page.type('input[name="pass"]', userPassword);

    // Submit the login form.
    await page.click('input[type="submit"]');

    // Wait for the page to finish loading.
    await page.waitForFunction(
      () => document.readyState === 'complete',
      { timeout: 10000 }
    );

    // Screenshot.
    await testBase.screenshot(
      page,
      'buyer-stores-page',
      await page.content()
    );

    testBase.assertInUrl(page, 'http://webserver/store/1');

    await page.goto(
      'http://webserver/account/buyer-verification',
      {
        waitUntil: 'networkidle2'
      }
    );

    await testBase.screenshot(
      page,
      'buyer-verification-form',
      await page.content()
    );

    // Source assertions.
    await testBase.assertInSourceCode(
      page,
      'Paste the verification code exactly as provided.'
    );

    await page.goto(
      'http://webserver/store/1/shop',
      {
        waitUntil: 'networkidle2'
      }
    );

    await testBase.screenshot(
      page,
      'buyer-store-shop',
      await page.content()
    );

    // Source assertions.
    await testBase.assertInSourceCode(
      page,
      'Add to cart'
    );


  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
