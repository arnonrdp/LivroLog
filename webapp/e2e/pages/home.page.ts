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
    await this.page.locator('[data-testid="book-search-button"]').click()
  }

  async clickOnBookResult(index: number = 0) {
    await this.page.locator('[data-testid="book-result"]').nth(index).click()
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
}
