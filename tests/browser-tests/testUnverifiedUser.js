const { expect } = require('chai')
const fs = require('fs')
const testBase = require('./testBase.js')

it('unverified should see continue as a buyer and continue has a seller button', async function() {
  this.timeout(25000);
  const puppeteer = require('puppeteer')
  const browser = await puppeteer.launch({
     headless: true,
     args: ['--no-sandbox', '--disable-setuid-sandbox']
  })
  var result = false
  const page = await browser.newPage()
  try {
    await page.setViewport({
        width: 1280,
        height: 800
      });

      // Login through custom Drupal endpoint.
      const loginResponse = await page.evaluate(async () => {
        const response = await fetch('/my-custom-module/test-login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            username: 'test_unverified',
            password: process.env.DRUPALPASS
          })
        });

        return {
          status: response.status,
          body: await response.json()
        };
      });

      if (!loginResponse.body.success) {
        throw new Error(
          `Login failed: ${JSON.stringify(loginResponse.body)}`
        );
      }

    console.log('Logged in:', loginResponse.body);
    // unverified should see continue as a buyer and continue has a seller

    // continue as a seller should see

    // administrator to add you to the store and enable seller role

    // If buyer then verification form  should be seen.
  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
