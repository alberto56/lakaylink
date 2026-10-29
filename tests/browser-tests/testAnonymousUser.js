const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')
const BASE_URL = 'http://webserver'


it('Anonymous user should see Login by google button', async function() {
  this.timeout(35000);
  const puppeteer = require('puppeteer')
  const browser = await puppeteer.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
  })
  var result = false
  const page = await browser.newPage()
  try {
    console.log('Testing ' + __filename)
    console.log('set viewport')
    await page.setViewport({ width: 1280, height: 800 })
    console.log('go to the login page')
    await page.goto(BASE_URL)

    await testBase.screenshot(page, 'custom-login-page', await page.content());
    await testBase.assertInSourceCode(page, 'Sign in With Google')

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});

it('Anonymous user should see Login by google button, onclick redirect to google accounts page ', async function() {
  this.timeout(35000);
  const puppeteer = require('puppeteer')
  const browser = await puppeteer.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
  })
  var result = false
  const page = await browser.newPage()
  try {
    console.log('Testing ' + __filename)
    console.log('set viewport')
    await page.setViewport({ width: 1280, height: 800 })
    console.log('go to the login page')
    await page.goto(BASE_URL + '/custom-login');

    await testBase.screenshot(page, 'custom-login-page', await page.content());
    await testBase.assertInSourceCode(page, 'Sign in With Google')
    await page.click('a[href="/user/login/google"]');
    await testBase.screenshot(page, 'google oauth page', await page.content());
    testBase.assertStartWithUrl(page, "https://accounts.google.com/signin/oauth/error");

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});

it('Anonymous user should see Login by google button in french, onclick redirect to google accounts page ', async function() {
  this.timeout(35000);
  const puppeteer = require('puppeteer')
  const browser = await puppeteer.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
  })
  var result = false
  const page = await browser.newPage()
  try {
    console.log('Testing ' + __filename)
    console.log('set viewport')
    await page.setViewport({ width: 1280, height: 800 })
    console.log('go to the login page')
    await page.goto(BASE_URL + '/fr/custom-login');

    await testBase.screenshot(page, 'fr-custom-login-page', await page.content());
    await testBase.assertInSourceCode(page, 'Se connecter avec Google')

    await page.click('a[href="/fr/user/login/google"]');
    await testBase.screenshot(page, 'fr google oauth page', await page.content());

    testBase.assertStartWithUrl(page, "https://accounts.google.com/signin/oauth/error");

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});

it('API should return currency exchange rate for simulated IP', async function() {
  this.timeout(35000)

  const puppeteer = require('puppeteer');
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();

  try {
    console.log('Testing ' + __filename);
    console.log('Calling get-ip-to-currency-rate API');

    const response = await page.goto(
      BASE_URL + '/api/get-ip-to-currency-rate?SIMULATE-IP=176.31.84.249'
    );

    expect(response.status()).to.equal(200);

    const data = await response.json();

    console.log('API response:', data);

    // Verify response structure
    expect(data).to.have.property('rates');
    expect(data.rates).to.have.property('usd-eur');

    // Exchange rate may vary, but must be a valid positive number
    expect(data.rates['usd-eur']).to.be.a('number');
    expect(data.rates['usd-eur']).to.be.greaterThan(0);

    // Date changes, so validate its format instead of its exact value
    expect(data).to.have.property('date');
    expect(data.date).to.match(
      /^\d{4}-\d{2}-\d{2}-\d{2}:\d{2}:\d{2} UTC$/
    );

    // Verify the expected currency
    expect(data['client-currency']).to.equal('EUR');

  } catch (error) {
    await testBase.showError(error, browser, page)
    throw error
  } finally {
    await browser.close()
  }
});
