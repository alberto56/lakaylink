// const { expect } = require('chai')
// const fs = require('fs')
// const testBase = require('./testBase.js')
// const BASE_URL = 'http://webserver'

// it('unverified user should see buyer verifucation form when he click on continue as buyer', async function() {
//   this.timeout(60000);
//   const puppeteer = require('puppeteer')
//   const browser = await puppeteer.launch({
//      headless: true,
//      args: ['--no-sandbox', '--disable-setuid-sandbox']
//   })
//   // var result = false
//   const page = await browser.newPage()
//   try {
//     await page.setViewport({
//       width: 1280,
//       height: 800
//     });

//     const userPassword = process.env.DRUPALPASS;

//     await page.goto(BASE_URL + '/test-user-login');

//     await testBase.screenshot(
//       page,
//       'unverified-user-test-login-buyer',
//       await page.content()
//     );

//     // Fill username and password.
//     await page.type('input[name="name"]', 'test_unverified');
//     await page.type('input[name="pass"]', userPassword);

//     // Submit the login form.
//     await page.click('form.my-custom-module-custom-login input[type="submit"]');

//     // await new Promise(resolve => setTimeout(resolve, 60000));
//     // await Promise.all([
//     //   page.waitForNavigation({
//     //     waitUntil: 'networkidle2',
//     //     timeout: 60000
//     //   }),
//     //   page.click('form.my-custom-module-custom-login input[type="submit"]')
//     // ]);
//     await page.waitForSelector('Continue as Buyer');

//     // Screenshot.
//     await testBase.screenshot(
//       page,
//       'unverified-login-redirect-after-buyer',
//       await page.content()
//     );

//     // Source assertions.
//     await testBase.assertInSourceCode(
//       page,
//       'Continue as Buyer'
//     );

//     // Click "Continue as Buyer".
//     await page.waitForSelector('a[href="/buyer-login-redirect"]');

//     const link = await page.$('a[href="/buyer-login-redirect"]');

//     if (link) {
//       console.log(await link.evaluate(el => ({
//         text: el.innerText,
//         href: el.href,
//         visible: !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length)
//       })));
//     }

//     // await page.click('a[href="/home/seller"]');
//     await page.evaluate(() => {
//         const link = document.querySelector('a[href="/buyer-login-redirect"]');
//         link.click();
//       });

//     await new Promise(resolve => setTimeout(resolve, 20000));

//     await testBase.assertInUrl(page, BASE_URL + '/account/buyer-verification');

//     // Screenshot.
//     await testBase.screenshot(
//       page,
//       'unverified-login-continue-as-buyer-verification-form',
//       await page.content()
//     );

//     // confirm buyer virification form.
//     // Source assertions.
//     await testBase.assertInSourceCode(
//       page,
//       'Verification code'
//     );

//   }
//   catch (error) {
//     await testBase.showError(error, browser, page);
//   }
//   await browser.close()
// });
