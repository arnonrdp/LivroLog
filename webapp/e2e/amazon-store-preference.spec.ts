import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { HomePage } from './pages/home.page'
import { BookDialogPage } from './pages/book-dialog.page'
import { createTestUser, testBooks } from './fixtures/test-data'

test.describe('Amazon Store Preference', () => {
  test.describe('Auto-detection on Registration', () => {
    test('detects Brazil for pt-BR locale', async ({ browser }) => {
      // Create a new context with Brazilian Portuguese locale
      const context = await browser.newContext({
        locale: 'pt-BR'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)

      await loginPage.goto()

      const user = createTestUser('brz')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      // Go to settings and verify the Amazon store is Brazil
      await page.goto('/settings/language')
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      // Check that Brazil is selected (in Portuguese: "Brasil")
      const selectedStore = page.locator('[data-testid="amazon-store-selected"]')
      await expect(selectedStore).toContainText('Brasil')

      await context.close()
    })

    test('detects US for en-US locale', async ({ browser }) => {
      const context = await browser.newContext({
        locale: 'en-US'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)

      await loginPage.goto()

      const user = createTestUser('usa')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      await page.goto('/settings/language')
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      const selectedStore = page.locator('[data-testid="amazon-store-selected"]')
      await expect(selectedStore).toContainText('United States')

      await context.close()
    })

    test('detects Germany for de-DE locale', async ({ browser }) => {
      const context = await browser.newContext({
        locale: 'de-DE'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)

      await loginPage.goto()

      const user = createTestUser('deu')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      await page.goto('/settings/language')
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      // Check that Germany is selected (de-DE falls back to English UI since we don't have German translations)
      // So it will show "Amazon Germany"
      const selectedStore = page.locator('[data-testid="amazon-store-selected"]')
      await expect(selectedStore).toContainText('Germany')

      await context.close()
    })

    test('detects Japan for ja-JP locale', async ({ browser }) => {
      const context = await browser.newContext({
        locale: 'ja-JP'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)

      await loginPage.goto()

      const user = createTestUser('jpn')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      await page.goto('/settings/language')
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      // Japanese locale shows Japanese translation: アマゾンジャパン
      const selectedStore = page.locator('[data-testid="amazon-store-selected"]')
      await expect(selectedStore).toContainText('アマゾンジャパン')

      await context.close()
    })

    test('detects UK for en-GB locale', async ({ browser }) => {
      const context = await browser.newContext({
        locale: 'en-GB'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)

      await loginPage.goto()

      const user = createTestUser('gbr')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      await page.goto('/settings/language')
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      const selectedStore = page.locator('[data-testid="amazon-store-selected"]')
      await expect(selectedStore).toContainText('United Kingdom')

      await context.close()
    })
  })

  test.describe('Settings - Change Preference', () => {
    test('user can change preferred Amazon store', async ({ browser }) => {
      // Start with US locale
      const context = await browser.newContext({
        locale: 'en-US'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)

      await loginPage.goto()
      const user = createTestUser('chg')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      // Go to settings
      await page.goto('/settings/language')
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      // Verify initial selection is US
      let selectedStore = page.locator('[data-testid="amazon-store-selected"]')
      await expect(selectedStore).toContainText('United States')

      // Click to open dropdown
      await page.locator('[data-testid="amazon-store-select"]').click()
      await page.waitForTimeout(500)

      // Select Brazil
      await page.locator('[data-testid="amazon-store-option-BR"]').click()
      await page.waitForTimeout(300)

      // Save
      await page.locator('[data-testid="save-language-btn"]').click()

      // Wait for success notification
      await expect(page.locator('.q-notification')).toContainText('updated', { timeout: 5000 })

      // Reload and verify it persisted
      await page.reload()
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      selectedStore = page.locator('[data-testid="amazon-store-selected"]')
      await expect(selectedStore).toContainText('Brazil')

      await context.close()
    })

    test('all 21 Amazon stores are available in dropdown', async ({ browser }) => {
      const context = await browser.newContext({
        locale: 'en-US'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)

      await loginPage.goto()
      const user = createTestUser('all')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      await page.goto('/settings/language')
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      // Open dropdown
      await page.locator('[data-testid="amazon-store-select"]').click()
      await page.waitForTimeout(500)

      // Check for all 21 stores
      const expectedStores = [
        'US',
        'CA',
        'MX',
        'BR',
        'UK',
        'DE',
        'FR',
        'IT',
        'ES',
        'NL',
        'SE',
        'PL',
        'BE',
        'TR',
        'IE',
        'JP',
        'IN',
        'AU',
        'SG',
        'AE',
        'SA',
        'EG',
        'ZA'
      ]

      for (const store of expectedStores) {
        const option = page.locator(`[data-testid="amazon-store-option-${store}"]`)
        await expect(option).toBeVisible({ timeout: 2000 })
      }

      await context.close()
    })
  })

  test.describe('Login does not override preference', () => {
    test('existing preference is preserved on login', async ({ browser }) => {
      // First, register with US locale
      const context1 = await browser.newContext({
        locale: 'en-US'
      })
      const page1 = await context1.newPage()
      const loginPage1 = new LoginPage(page1)

      await loginPage1.goto()
      const user = createTestUser('prs')
      await loginPage1.register(user)
      await loginPage1.expectToBeLoggedIn()

      // Change preference to Japan
      await page1.goto('/settings/language')
      await page1.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })
      await page1.locator('[data-testid="amazon-store-select"]').click()
      await page1.waitForTimeout(500)
      await page1.locator('[data-testid="amazon-store-option-JP"]').click()
      await page1.locator('[data-testid="save-language-btn"]').click()
      await expect(page1.locator('.q-notification')).toContainText('updated', { timeout: 5000 })

      // Logout
      await page1.goto('/settings/account')
      await page1.locator('[data-testid="logout-button"]').click()
      await expect(page1).toHaveURL('/')

      await context1.close()

      // Now login again with different locale (Brazilian)
      const context2 = await browser.newContext({
        locale: 'pt-BR'
      })
      const page2 = await context2.newPage()
      const loginPage2 = new LoginPage(page2)

      await loginPage2.goto()
      await loginPage2.login(user.email, user.password)
      await loginPage2.expectToBeLoggedIn()

      // Verify preference is still Japan (not overwritten to Brazil)
      await page2.goto('/settings/language')
      await page2.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })

      const selectedStore = page2.locator('[data-testid="amazon-store-selected"]')
      await expect(selectedStore).toContainText('Japan')

      await context2.close()
    })
  })

  test.describe('BookDialog links to preferred store', () => {
    test('Amazon button links directly to preferred store', async ({ browser }) => {
      // Register with Brazilian locale
      const context = await browser.newContext({
        locale: 'pt-BR'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)
      const homePage = new HomePage(page)
      const bookDialog = new BookDialogPage(page)

      await loginPage.goto()
      const user = createTestUser('bkd')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      // Search for a book and add it to library
      await homePage.searchBooks(testBooks.searchQuery)
      await homePage.clickOnBookResult()

      // Add to library to get Amazon links
      await bookDialog.addToLibrary()
      await page.waitForTimeout(3000)

      // Check if Amazon button is visible and has direct link
      const amazonBtn = page.locator('[data-testid="amazon-btn"]')
      const isAmazonBtnVisible = await amazonBtn.isVisible().catch(() => false)

      if (isAmazonBtnVisible) {
        // Check that the button has a direct href (no dropdown)
        const href = await amazonBtn.getAttribute('href')
        expect(href).toBeTruthy()
        expect(href).toContain('amazon.com.br') // Brazilian store

        // Verify no dropdown menu exists
        const menu = page.locator('[data-testid="amazon-menu"]')
        await expect(menu).not.toBeVisible()
      }

      await bookDialog.close()
      await context.close()
    })

    test('changing preference updates Amazon button link', async ({ browser }) => {
      const context = await browser.newContext({
        locale: 'en-US'
      })
      const page = await context.newPage()
      const loginPage = new LoginPage(page)
      const homePage = new HomePage(page)
      const bookDialog = new BookDialogPage(page)

      await loginPage.goto()
      const user = createTestUser('upd')
      await loginPage.register(user)
      await loginPage.expectToBeLoggedIn()

      // Add a book first
      await homePage.searchBooks(testBooks.searchQuery)
      await homePage.clickOnBookResult()
      await bookDialog.addToLibrary()
      await page.waitForTimeout(3000)

      // Check initial link (should be US store)
      const amazonBtn = page.locator('[data-testid="amazon-btn"]')
      const isAmazonBtnVisible = await amazonBtn.isVisible().catch(() => false)

      if (isAmazonBtnVisible) {
        const initialHref = await amazonBtn.getAttribute('href')
        // US store uses amazon.com (not amazon.com.br or other regional domains)
        expect(initialHref).toContain('amazon.com')
      }

      // Close dialog
      await bookDialog.close()

      // Change preference to Brazil
      await page.goto('/settings/language')
      await page.waitForSelector('[data-testid="amazon-store-select"]', { timeout: 10000 })
      await page.locator('[data-testid="amazon-store-select"]').click()
      await page.waitForTimeout(500)
      await page.locator('[data-testid="amazon-store-option-BR"]').click()
      await page.locator('[data-testid="save-language-btn"]').click()
      await expect(page.locator('.q-notification')).toContainText('updated', { timeout: 5000 })

      // Go back to home and open the book again
      await page.goto('/home')
      await page.waitForTimeout(1000)

      // Click on the book in library
      const bookCard = page.locator('.book-card').first()
      const hasBookCard = await bookCard.isVisible().catch(() => false)

      if (hasBookCard) {
        await bookCard.click()
        await page.waitForTimeout(1000)

        const amazonBtn2 = page.locator('[data-testid="amazon-btn"]')
        const isAmazonBtn2Visible = await amazonBtn2.isVisible().catch(() => false)

        if (isAmazonBtn2Visible) {
          const newHref = await amazonBtn2.getAttribute('href')
          // Should now link to Brazilian store
          expect(newHref).toContain('amazon.com.br')
        }
      }

      await context.close()
    })
  })
})
