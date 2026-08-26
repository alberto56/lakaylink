const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')

it(
  'unverified should see continue as a buyer and continue has a seller button. ' +
  'click on continue as a seller.',
  async function() {
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
      'unverified-user-test-login',
      await page.content()
    );

    // Fill username and password.
    await page.type('input[name="name"]', 'test_unverified');
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
      'unverified-login-redirect',
      await page.content()
    );

    // Source assertions.
    await testBase.assertInSourceCode(
      page,
      'Continue as Buyer'
    );

    await testBase.assertInSourceCode(
      page,
      'Continue as Seller'
    );

    // Click "Continue as Seller".
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
      page.click('a[href="/home/seller"]')
    ]);

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

it('unverified user should see buyer verifucation form when he click on continue as buyer', async function() {
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
      'unverified-user-test-login',
      await page.content()
    );

    // Fill username and password.
    await page.type('input[name="name"]', 'test_unverified');
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
      'unverified-login-redirect',
      await page.content()
    );

    // Source assertions.
    await testBase.assertInSourceCode(
      page,
      'Continue as Buyer'
    );

    await testBase.assertInSourceCode(
      page,
      'Continue as Seller'
    );

    // Click "Continue as Buyer".
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
      page.click('a[href="/buyer-login-redirect"]')
    ]);

    // Screenshot.
    await testBase.screenshot(
      page,
      'unverified-login-continue-as-buyer-verification-form',
      await page.content()
    );

    // confirm buyer virification form.
    // Source assertions.
    await testBase.assertInSourceCode(
      page,
      'Verification code'
    );

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
