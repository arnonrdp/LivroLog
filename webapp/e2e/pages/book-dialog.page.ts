import { Page, expect } from '@playwright/test'

export class BookDialogPage {
  constructor(private page: Page) {}

  async addToLibrary() {
    await this.page.locator('[data-testid="add-to-library-btn"]').click()
    // Wait for the operation to complete
    await this.page.waitForTimeout(1000)
  }

  async removeFromLibrary() {
    await this.page.locator('[data-testid="remove-from-library-btn"]').click()
    await this.page.waitForTimeout(1000)
  }

  async setReadingStatus(status: 'read' | 'reading' | 'want_to_read' | 'abandoned' | 'on_hold' | 're_reading') {
    await this.page.locator('[data-testid="reading-status-select"]').click()
    // Quasar select uses q-item for options
    await this.page.locator('.q-menu .q-item').filter({ hasText: new RegExp(status.replace('_', ' '), 'i') }).click()
  }

  async setReadDate(date: string) {
    await this.page.locator('[data-testid="read-date-input"]').fill(date)
    // Trigger blur to save
    await this.page.locator('[data-testid="read-date-input"]').blur()
  }

  async setPrivate(isPrivate: boolean) {
    const checkbox = this.page.locator('[data-testid="private-book-checkbox"]')
    const isChecked = await checkbox.isChecked()
    if (isPrivate !== isChecked) {
      await checkbox.click()
    }
  }

  async writeReview(title: string, content: string, rating: number) {
    // Set rating by clicking on the rating stars
    await this.page.locator('[data-testid="review-rating"]').locator('.q-icon').nth(rating - 1).click()
    await this.page.locator('[data-testid="review-title-input"]').fill(title)
    await this.page.locator('[data-testid="review-content-input"]').fill(content)
    await this.page.locator('[data-testid="submit-review-btn"]').click()
    await this.page.waitForTimeout(1000)
  }

  async editReview(title: string, content: string) {
    // Reviews are edited inline - just fill the form again
    await this.page.locator('[data-testid="review-title-input"]').fill(title)
    await this.page.locator('[data-testid="review-content-input"]').fill(content)
    await this.page.locator('[data-testid="submit-review-btn"]').click()
    await this.page.waitForTimeout(1000)
  }

  async deleteReview() {
    await this.page.locator('[data-testid="delete-review-btn"]').click()
    await this.page.locator('[data-testid="confirm-delete-btn"]').click()
    await this.page.waitForTimeout(1000)
  }

  async close() {
    await this.page.locator('[data-testid="close-dialog-btn"]').click()
  }

  async expectInLibrary() {
    await expect(this.page.locator('[data-testid="remove-from-library-btn"]')).toBeVisible()
  }

  async expectNotInLibrary() {
    await expect(this.page.locator('[data-testid="add-to-library-btn"]')).toBeVisible()
  }

  async expectReviewVisible(title: string) {
    await expect(this.page.locator('[data-testid="user-review"]').filter({ hasText: title })).toBeVisible()
  }
}
