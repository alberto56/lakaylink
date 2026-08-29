const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')


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
      await page.goto('http://webserver')

      await testBase.screenshot(page, 'custom-login-page', await page.content());
      await testBase.assertInSourceCode(page, 'Sign in With Google')

    }
    catch (error) {
      await testBase.showError(error, browser, page);
    }
    await browser.close()
  });
