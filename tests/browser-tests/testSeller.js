const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')

it('seller should see store allocate to them.', async function() {
  this.timeout(25000);
  const puppeteer = require('puppeteer')
  const browser = await puppeteer.launch({
     headless: true,
     args: ['--no-sandbox', '--disable-setuid-sandbox']
  })
  var result = false
  const page = await browser.newPage()
  try {
    // seller login by username and password to run.  one time login

    // seller should see store allocate to them.

    // he should see generate code button.

    // shouldn't see add to cart form in product and product listing page

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
