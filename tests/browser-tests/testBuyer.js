const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')

it('buyer should see the store he has joined.', async function() {
  this.timeout(35000);
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

    await page.goto('http://webserver/test-user-login');

    await testBase.screenshot(
      page,
      'buyer-user-test-login',
      await page.content()
    );

    // Fill username and password.
    await page.type('input[name="name"]', 'test_buyer');
    await page.type('input[name="pass"]', userPassword);

    // Submit.
    await page.click('form.my-custom-module-custom-login input[type="submit"]');

    await testBase.screenshot(
      page,
      'buyer-after-login-page',
      await page.content()
    );

    // Screenshot.
    await testBase.screenshot(
      page,
      'buyer-stores-listed-view-page',
      await page.content()
    );

    testBase.assertInUrl(page, 'http://webserver/store/1');

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
