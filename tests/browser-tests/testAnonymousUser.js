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
