const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')

it('Anonymous user product page redirects to custom login', async function() {
  this.timeout(25000);
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
    await page.goto('http://webserver/test-product')

    await testBase.screenshot(page, 'custom login page', await page.content());
    await testBase.assertInSourceCode(page, 'Sign in With Google')

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});

it('Anonymous user should access products api', async function () {
  this.timeout(25000);

  const puppeteer = require('puppeteer');

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();

  try {
    console.log('Testing ' + __filename);

    await page.setViewport({
      width: 1280,
      height: 800
    });

    const apiUrl =
      'http://webserver/api/product/grocery?include=variations';

    const response = await page.goto(apiUrl);

    expect(response.status()).to.equal(200);

    const json = await response.json();

    const { data = [], included = [] } = json;

    console.log(`Products: ${data.length}`);
    console.log(`Included entities: ${included.length}`);

    console.log("-------- data -----------");
    console.log(data);

    // Products should not be empty.
    testBase.assertNotEmpty(
      data,
      'Products API should return products'
    );

    // Get grocery variations from included entities.
    const variations = included.filter(
      item =>
        item.type === 'commerce_product_variation--grocery_variation'
    );

    console.log(`Variations: ${variations.length}`);

    // Variations should not be empty.
    testBase.assertNotEmpty(
      variations,
      'Products API should return product variations'
    );

  } catch (error) {
    await testBase.showError(error, browser, page);
    throw error;
  } finally {
    await browser.close();
  }
});
