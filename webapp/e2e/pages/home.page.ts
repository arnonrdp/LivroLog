import { Page, expect } from '@playwright/test'

export class HomePage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/home')
  }

  async gotoAdd() {
    await this.page.goto('/add')
  }

  async searchBooks(query: string) {
    await this.gotoAdd()
    await this.page.locator('[data-testid="book-search-input"]').fill(query)
    // Submit the form by pressing Enter (more reliable than clicking the button)
    await this.page.locator('[data-testid="book-search-input"]').press('Enter')
    // Wait for results to load (API call can be slow)
    await this.page.waitForSelector('[data-testid="book-result"]', { timeout: 60000 })
  }

  async clickOnBookResult(index: number = 0) {
    // Click on the book cover to open the BookDialog
    await this.page.locator('[data-testid="book-result"]').nth(index).locator('.book-cover').click()
    // Wait for dialog to appear
    await this.page.waitForSelector('[data-testid="close-dialog-btn"]', { timeout: 10000 })
  }

  async expectSearchResults() {
    await expect(this.page.locator('[data-testid="book-result"]').first()).toBeVisible()
  }

  async logout() {
    // Navigate to settings/account to logout
    await this.page.goto('/settings/account')
    await this.page.locator('[data-testid="logout-button"]').click()
  }

  async goToSettings() {
    await this.page.goto('/settings')
  }

  async expectBookInLibrary(bookTitle: string) {
    await this.page.goto('/home')
    await expect(this.page.locator('[data-testid="library-book"]').filter({ hasText: bookTitle })).toBeVisible()
  }

  async expectBookNotInLibrary(bookTitle: string) {
    await this.page.goto('/home')
    await expect(this.page.locator('[data-testid="library-book"]').filter({ hasText: bookTitle })).not.toBeVisible()
  }

  async clickOnLibraryBook(index: number = 0) {
    await this.page.goto('/home')
    // Wait for library books to load
    await this.page.waitForSelector('[data-testid="library-book"]', { timeout: 10000 })
    // Click on the book cover to open the BookDialog
    await this.page.locator('[data-testid="library-book"]').nth(index).locator('.book-cover').click()
    // Wait for dialog to appear
    await this.page.waitForSelector('[data-testid="close-dialog-btn"]', { timeout: 10000 })
  }
}
