const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')
const BASE_URL = 'http://webserver'

it(
  'unverified should see continue as a buyer and continue has a seller button. ' +
  'click on continue as a seller.',
  async function() {
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
      'unverified-user-test-login-seller',
      await page.content()
    );

    // Fill username and password.
    await page.type('input[name="name"]', 'test_unverified');
    await page.type('input[name="pass"]', userPassword);

    // Screenshot.
    await testBase.screenshot(
      page,
      'unverified-user-test-login-seller-before-submit',
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

    // Screenshot.
    await testBase.screenshot(
      page,
      'unverified-login-redirect-after-seller',
      await page.content()
    );

    await testBase.assertInSourceCode(
      page,
      'Continue as Seller'
    );

    // Click "Continue as Seller".
    await page.waitForSelector('a[href="/home/seller"]');

    const link = await page.$('a[href="/home/seller"]');

    if (link) {
      console.log(await link.evaluate(el => ({
        text: el.innerText,
        href: el.href,
        visible: !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length)
      })));
    }

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
      page.click('a[href="/home/seller"]')
    ]);

    await testBase.assertInUrl(
      page,
      BASE_URL + '/home/seller'
    );

    // Screenshot.
    await testBase.screenshot(
      page,
      'unverified-login-continue-as-seller-home',
      await page.content()
    );

    // Ask an administrator to provide seller access and associate you with at least one store.
    // Source assertions.
    await testBase.assertInSourceCode(
      page,
      'Ask an administrator to provide seller access and associate you with at least one store.'
    );

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
