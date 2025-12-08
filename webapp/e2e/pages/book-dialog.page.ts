import { Page, expect } from '@playwright/test'

export class BookDialogPage {
  constructor(private page: Page) {}

  async addToLibrary() {
    // Scroll the button into view and wait for it to be visible
    const addBtn = this.page.locator('[data-testid="add-to-library-btn"]')
    await addBtn.scrollIntoViewIfNeeded()
    await addBtn.waitFor({ state: 'visible', timeout: 15000 })
    await addBtn.click()
    // Wait for the operation to complete - button should change to remove
    await this.page.waitForSelector('[data-testid="remove-from-library-btn"]', { timeout: 15000 })
  }

  async removeFromLibrary() {
    await this.page.locator('[data-testid="remove-from-library-btn"]').click()
    // Book is removed immediately (no confirmation dialog)
    // Wait for button to change back to add
    await this.page.waitForSelector('[data-testid="add-to-library-btn"]', { timeout: 10000 })
  }

  async setReadingStatus(status: 'read' | 'reading' | 'want_to_read' | 'abandoned' | 'on_hold' | 're_reading') {
    await this.page.locator('[data-testid="reading-status-select"]').click()
    // Map status to exact display text
    const statusMap: Record<string, string> = {
      read: 'Read',
      reading: 'Reading',
      want_to_read: 'Want to Read',
      abandoned: 'Abandoned',
      on_hold: 'On Hold',
      re_reading: 'Re-reading'
    }
    const displayText = statusMap[status]
    // Use getByRole for exact matching
    await this.page.getByRole('option', { name: displayText, exact: true }).click()
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
    // Set rating first - use getByRole for accessibility-based selection
    // The q-rating shows "star N" labels for each star
    await this.page.getByRole('radio', { name: `star ${rating}` }).click()
    await this.page.waitForTimeout(300)

    // Fill the title field
    const titleInput = this.page.locator('[data-testid="review-title-input"]')
    await titleInput.click()
    await titleInput.fill(title)
    await this.page.waitForTimeout(200)

    // Fill the content field - this is required
    const contentInput = this.page.locator('[data-testid="review-content-input"]')
    await contentInput.click()
    await contentInput.fill(content)
    await this.page.waitForTimeout(200)

    // Submit the review
    await this.page.locator('[data-testid="submit-review-btn"]').click()
    // Wait for the review to be saved and appear in the list
    await this.page.waitForTimeout(3000)
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

  async expectReviewVisible(textToFind: string) {
    // Wait longer for the review to appear and look for any review containing the text
    await expect(this.page.locator('[data-testid="user-review"], [data-testid="book-review"]').filter({ hasText: textToFind })).toBeVisible({
      timeout: 10000
    })
  }

  async expectUserReviewExists() {
    // Just check if the user has any review on this book
    await expect(this.page.locator('[data-testid="user-review"]')).toBeVisible({ timeout: 10000 })
  }
}
