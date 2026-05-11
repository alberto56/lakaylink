const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')

it('Anonymous user should see Login to buy button', async function() {
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

    await testBase.screenshot(page, 'product page', await page.content());
    await testBase.assertInSourceCode(page, 'Login to buy')

  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});


it('Anonymous user should see Login by google button', async function() {
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
      await page.goto('http://webserver')
  
      await testBase.screenshot(page, 'product page', await page.content());
      await testBase.assertInSourceCode(page, 'Authenticate through Google')

    }
    catch (error) {
      await testBase.showError(error, browser, page);
    }
    await browser.close()
  });
