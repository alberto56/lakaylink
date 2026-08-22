const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')

it('buyer should redirect to store', async function() {
  this.timeout(25000);
  const puppeteer = require('puppeteer')
  const browser = await puppeteer.launch({
     headless: true,
     args: ['--no-sandbox', '--disable-setuid-sandbox']
  })
  var result = false
  const page = await browser.newPage()
  try {
    // buyer should redirect to store
    // should see buyerVerification Form.
    // should see add to cart form in product and product listing page
  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
