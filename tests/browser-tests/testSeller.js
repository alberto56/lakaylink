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
      'seller-user-test-login',
      await page.content()
    );

    // Fill username and password.
    await page.type('input[name="name"]', 'test_seller');
    await page.type('input[name="pass"]', userPassword);

    // Submit the login form.
    await page.click('input[type="submit"]');

    // Screenshot.
    await testBase.screenshot(
      page,
      'seller-stores-list-page',
      await page.content()
    );

    // Get the complete page source.
    const source = await page.content();
    await testBase.assertInSourceCode(
      page,
      '<h1 class="mb-4">My Stores</h1>'
    );

    // Verify Generate Invitation Code.
    await testBase.assertInSourceCode(
      page,
      'Generate Invitation Code'
    );

    // Verify WhatsApp instruction.
    await testBase.assertInSourceCode(
      page,
      'Send this code to the buyer by WhatsApp so they start buying on the store'
    );

    // Find all "Generate Invitation Code" links.
    const generateCodeLinks = await page.$x(
      "//a[contains(normalize-space(), 'Generate Invitation Code')]"
    );

    expect(generateCodeLinks.length).to.be.greaterThan(0);

    // Click any one of them.
    await generateCodeLinks[0].click();

    // Wait for the AJAX request to populate an invitation code.
    await page.waitForFunction(() => {
      const elements = document.querySelectorAll(
        '[id^="invitation-code-"]'
      );

      return Array.from(elements).some(
        element => element.textContent.trim().length > 0
      );
    }, {
      timeout: 10000
    });

    // Get the generated invitation code.
    const invitationCode = await page.evaluate(() => {
      const elements = document.querySelectorAll(
        '[id^="invitation-code-"]'
      );

      const element = Array.from(elements).find(
        element => element.textContent.trim().length > 0
      );

      return element ? element.textContent.trim() : '';
    });

    expect(invitationCode).to.not.equal('');

    // Screenshot.
    await testBase.screenshot(
      page,
      'seller-stores-list-page-generated-invitation-code',
      await page.content()
    );
  }
  catch (error) {
    await testBase.showError(error, browser, page);
  }
  await browser.close()
});
