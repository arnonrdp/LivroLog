import { Page, expect } from '@playwright/test'

export class SettingsPage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/settings')
  }

  async gotoTags() {
    await this.page.goto('/settings/tags')
    await this.page.waitForSelector('[data-testid="create-tag-btn"]', { timeout: 10000 })
  }

  async gotoBooks() {
    await this.page.goto('/settings/books')
  }

  // Tag Management
  async createTag(name: string, colorIndex: number = 0) {
    await this.page.locator('[data-testid="create-tag-btn"]').click()
    await this.page.waitForSelector('[data-testid="tag-dialog"]', { timeout: 5000 })

    await this.page.locator('[data-testid="tag-name-input"]').fill(name)

    // QColor palette uses .q-color-picker__cube elements for each color
    // The first color is already selected by default, so we only need to click if colorIndex > 0
    if (colorIndex > 0) {
      const colorPicker = this.page.locator('[data-testid="tag-color-picker"]')
      const colorButtons = colorPicker.locator('.q-color-picker__cube')
      await colorButtons.nth(colorIndex).click()
    }

    await this.page.locator('[data-testid="save-tag-btn"]').click()
    await this.page.waitForSelector('[data-testid="tag-dialog"]', { state: 'hidden', timeout: 5000 })
  }

  async editTag(tagName: string, newName?: string, newColorIndex?: number) {
    const tagRow = this.page.locator('[data-testid="tag-row"]').filter({ hasText: tagName })
    await tagRow.locator('[data-testid="edit-tag-btn"]').click()
    await this.page.waitForSelector('[data-testid="tag-dialog"]', { timeout: 5000 })

    if (newName) {
      await this.page.locator('[data-testid="tag-name-input"]').fill(newName)
    }

    if (newColorIndex !== undefined) {
      // QColor palette uses .q-color-picker__cube elements for each color
      const colorPicker = this.page.locator('[data-testid="tag-color-picker"]')
      const colorButtons = colorPicker.locator('.q-color-picker__cube')
      await colorButtons.nth(newColorIndex).click()
    }

    await this.page.locator('[data-testid="save-tag-btn"]').click()
    await this.page.waitForSelector('[data-testid="tag-dialog"]', { state: 'hidden', timeout: 5000 })
  }

  async deleteTag(tagName: string) {
    const tagRow = this.page.locator('[data-testid="tag-row"]').filter({ hasText: tagName })
    await tagRow.locator('[data-testid="delete-tag-btn"]').click()

    // Confirm deletion
    await this.page.waitForSelector('[data-testid="confirm-delete-dialog"]', { timeout: 5000 })
    await this.page.locator('[data-testid="confirm-delete-btn"]').click()
    await this.page.waitForSelector('[data-testid="confirm-delete-dialog"]', { state: 'hidden', timeout: 5000 })
  }

  async expectTagExists(tagName: string) {
    await expect(this.page.locator('[data-testid="tag-row"]').filter({ hasText: tagName })).toBeVisible()
  }

  async expectTagNotExists(tagName: string) {
    await expect(this.page.locator('[data-testid="tag-row"]').filter({ hasText: tagName })).not.toBeVisible()
  }

  async expectTagCount(count: number) {
    await expect(this.page.locator('[data-testid="tag-row"]')).toHaveCount(count)
  }

  async getTagBooksCount(tagName: string): Promise<string> {
    const tagRow = this.page.locator('[data-testid="tag-row"]').filter({ hasText: tagName })
    return await tagRow.locator('[data-testid="tag-books-count"]').textContent() || '0'
  }
}
