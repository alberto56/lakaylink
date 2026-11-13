const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')
const BASE_URL = 'http://webserver'

it('unverified user should see buyer verification form when he click on continue as buyer', async function() {
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

    await page.goto(BASE_URL + '/test-user-login');

    await testBase.screenshot(
      page,
      'unverified-user-test-login-buyer',
      await page.content()
    );

    // Fill username and password.
    await page.type('input[name="name"]', 'test_unverified');
    await page.type('input[name="pass"]', userPassword);


    // Screenshot.
    await testBase.screenshot(
      page,
      'unverified-user-test-login-buyer-before-submit',
      await page.content()
    );

    // Submit the login form.
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
      page.click('form.my-custom-module-custom-login input[type="submit"]')
    ]);

    console.log("--- URL ---:", await page.url());

    if ((await page.url()).includes('/test-user-login')) {
      console.log(await page.content());
      throw new Error("Login failed");
    }

    await testBase.screenshot(
      page,
      'unverified-login-redirect-after-buyer',
      await page.content()
    );

    // Source assertions.
    testBase.assertInUrls(page, BASE_URL + '/home', BASE_URL + '/store/1');

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
