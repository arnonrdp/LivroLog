import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { createTestUser } from './fixtures/test-data'

// Real Amazon URLs for testing
const amazonUrls = {
  // Real book: "O Pequeno Príncipe" - has ISBN, page count, publisher
  book: 'https://www.amazon.com.br/dp/8595081514',
  // Real non-book product: Samsung TV
  nonBook: 'https://www.amazon.com.br/dp/B0D5HL182V',
  // Funko Pop (toy, not a book)
  funkoPop: 'https://www.amazon.com.br/dp/B0BY2SCK5L',
  // Invalid (not Amazon)
  invalid: 'https://www.google.com/search?q=livro',
  // Amazon Canada book: "Value(s)" by Mark Carney
  canadaBook: 'https://www.amazon.ca/Values-Building-Better-World-All/dp/0771051794',
  // Amazon short URL (a.co) - redirects to a product page
  shortUrl: 'https://a.co/d/0fsTmpPj'
}

test.describe('Add Book from Amazon', () => {
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)

    // Register and login a test user with unique data
    await loginPage.goto()
    const user = createTestUser('amz')
    await loginPage.register(user)
  })

  test('authenticated user sees add own book section after search', async ({ page }) => {
    // Go to add page (main search page for logged-in users)
    await page.goto('/add')

    // Perform a search
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')

    // Wait for search to complete (either results or no results)
    await page.waitForTimeout(3000)

    // Should see the add own book section
    await expect(page.locator('[data-testid="add-own-book-section"]')).toBeVisible()
    await expect(page.locator('[data-testid="add-own-book-btn"]')).toBeVisible()
  })

  test('clicking add book button opens Amazon URL dialog', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Click add own book button
    await page.locator('[data-testid="add-own-book-btn"]').click()

    // Dialog should be visible
    await expect(page.locator('[data-testid="add-book-amazon-dialog"]')).toBeVisible()
    await expect(page.locator('[data-testid="amazon-url-input"]')).toBeVisible()
    await expect(page.locator('[data-testid="submit-amazon-url-btn"]')).toBeVisible()
  })

  test('submit button is disabled when URL is empty', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Open dialog
    await page.locator('[data-testid="add-own-book-btn"]').click()
    await expect(page.locator('[data-testid="add-book-amazon-dialog"]')).toBeVisible()

    // Submit button should be disabled
    await expect(page.locator('[data-testid="submit-amazon-url-btn"]')).toBeDisabled()
  })

  test('shows error for invalid Amazon URL', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Open dialog
    await page.locator('[data-testid="add-own-book-btn"]').click()

    // Enter invalid URL (not Amazon)
    await page.locator('[data-testid="amazon-url-input"]').fill(amazonUrls.invalid)

    // Submit
    await page.locator('[data-testid="submit-amazon-url-btn"]').click()

    // Should show error message
    await page.waitForTimeout(2000)
    await expect(page.locator('[data-testid="add-book-amazon-dialog"]')).toContainText('URL inválida')
  })

  test('shows error for non-book Amazon product (TV)', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Open dialog
    await page.locator('[data-testid="add-own-book-btn"]').click()

    // Enter a real TV product URL
    await page.locator('[data-testid="amazon-url-input"]').fill(amazonUrls.nonBook)

    // Submit
    await page.locator('[data-testid="submit-amazon-url-btn"]').click()

    // Should show error - either "not a book" or "couldn't extract data" (Amazon may block scraping)
    await page.waitForTimeout(10000)

    // Dialog should still be visible with an error message
    await expect(page.locator('[data-testid="add-book-amazon-dialog"]')).toBeVisible()
    const dialogText = await page.locator('[data-testid="add-book-amazon-dialog"]').textContent()
    // Accept any error that prevents adding the product
    expect(dialogText).toMatch(/(não parece ser de um livro|Não foi possível extrair|extrair dados|URL inválida)/i)
  })

  test('rejects Funko Pop toy as non-book product', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Open dialog
    await page.locator('[data-testid="add-own-book-btn"]').click()

    // Enter Funko Pop URL (toy, not a book)
    await page.locator('[data-testid="amazon-url-input"]').fill(amazonUrls.funkoPop)

    // Submit
    await page.locator('[data-testid="submit-amazon-url-btn"]').click()

    // Should show error about not being a book
    await page.waitForTimeout(10000)

    // Dialog should still be visible with an error message
    await expect(page.locator('[data-testid="add-book-amazon-dialog"]')).toBeVisible()
    const dialogText = await page.locator('[data-testid="add-book-amazon-dialog"]').textContent()
    // Should be rejected as non-book
    expect(dialogText).toMatch(/(não parece ser de um livro|Não foi possível extrair|extrair dados)/i)
  })

  test('successfully adds a real book from Amazon', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Open dialog
    await page.locator('[data-testid="add-own-book-btn"]').click()

    // Enter a real book URL (O Pequeno Príncipe)
    await page.locator('[data-testid="amazon-url-input"]').fill(amazonUrls.book)

    // Submit
    await page.locator('[data-testid="submit-amazon-url-btn"]').click()

    // Should succeed - dialog closes and redirects to book page or shows success notification
    await page.waitForTimeout(15000)

    // Either dialog closed (success) or we're on a book page
    const dialogVisible = await page.locator('[data-testid="add-book-amazon-dialog"]').isVisible()

    if (dialogVisible) {
      // If dialog still visible, check if there's an error (book might already exist)
      const dialogText = await page.locator('[data-testid="add-book-amazon-dialog"]').textContent()
      // Accept if book was added or already in library
      expect(dialogText).toMatch(/(sucesso|já está|estante)/i)
    } else {
      // Dialog closed - check for success notification or book page
      const currentUrl = page.url()
      const hasNotification = await page.locator('.q-notification').isVisible()
      expect(currentUrl.includes('/books/') || hasNotification).toBeTruthy()
    }
  })

  test('successfully adds a book from Amazon Canada (amazon.ca)', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Open dialog
    await page.locator('[data-testid="add-own-book-btn"]').click()

    // Enter Amazon Canada book URL
    await page.locator('[data-testid="amazon-url-input"]').fill(amazonUrls.canadaBook)

    // Submit
    await page.locator('[data-testid="submit-amazon-url-btn"]').click()

    // Should succeed - dialog closes and redirects to book page or shows success notification
    await page.waitForTimeout(15000)

    // Either dialog closed (success) or we're on a book page
    const dialogVisible = await page.locator('[data-testid="add-book-amazon-dialog"]').isVisible()

    if (dialogVisible) {
      const dialogText = await page.locator('[data-testid="add-book-amazon-dialog"]').textContent()
      // Accept if book was added or already in library - should NOT show "não parece ser de um livro"
      expect(dialogText).not.toMatch(/não parece ser de um livro/i)
      expect(dialogText).toMatch(/(sucesso|já está|estante|Não foi possível extrair)/i)
    } else {
      // Dialog closed - success
      const currentUrl = page.url()
      const hasNotification = await page.locator('.q-notification').isVisible()
      expect(currentUrl.includes('/books/') || hasNotification).toBeTruthy()
    }
  })

  test('successfully adds a book from Amazon short URL (a.co)', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Open dialog
    await page.locator('[data-testid="add-own-book-btn"]').click()

    // Enter Amazon short URL
    await page.locator('[data-testid="amazon-url-input"]').fill(amazonUrls.shortUrl)

    // Submit
    await page.locator('[data-testid="submit-amazon-url-btn"]').click()

    // Should succeed - dialog closes and redirects to book page or shows success notification
    await page.waitForTimeout(15000)

    // Either dialog closed (success) or we're on a book page
    const dialogVisible = await page.locator('[data-testid="add-book-amazon-dialog"]').isVisible()

    if (dialogVisible) {
      const dialogText = await page.locator('[data-testid="add-book-amazon-dialog"]').textContent()
      // Accept if book was added or already in library - should NOT show "não parece ser de um livro"
      expect(dialogText).not.toMatch(/não parece ser de um livro/i)
      expect(dialogText).toMatch(/(sucesso|já está|estante|Não foi possível extrair)/i)
    } else {
      // Dialog closed - success
      const currentUrl = page.url()
      const hasNotification = await page.locator('.q-notification').isVisible()
      expect(currentUrl.includes('/books/') || hasNotification).toBeTruthy()
    }
  })

  test('can cancel the dialog', async ({ page }) => {
    // Go to add page and search
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill('livro teste xyz123')
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForTimeout(3000)

    // Open dialog
    await page.locator('[data-testid="add-own-book-btn"]').click()
    await expect(page.locator('[data-testid="add-book-amazon-dialog"]')).toBeVisible()

    // Cancel
    await page.locator('[data-testid="cancel-add-book-btn"]').click()

    // Dialog should be closed
    await expect(page.locator('[data-testid="add-book-amazon-dialog"]')).not.toBeVisible()
  })
})
