import { Page, expect } from '@playwright/test'

export class ProfilePage {
  constructor(private page: Page) {}

  async goto(username: string) {
    await this.page.goto(`/${username}`)
  }

  async follow() {
    await this.page.locator('[data-testid="follow-button"]').click()
    await this.page.waitForTimeout(1000)
  }

  async unfollow() {
    await this.page.locator('[data-testid="unfollow-button"]').click()
    // Handle confirmation dialog if present
    const confirmBtn = this.page.locator('.q-dialog .q-btn').filter({ hasText: /confirm/i })
    if (await confirmBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
      await confirmBtn.click()
    }
    await this.page.waitForTimeout(1000)
  }

  async expectFollowButton() {
    await expect(this.page.locator('[data-testid="follow-button"]')).toBeVisible()
  }

  async expectUnfollowButton() {
    await expect(this.page.locator('[data-testid="unfollow-button"]')).toBeVisible()
  }

  async expectPendingButton() {
    await expect(this.page.locator('[data-testid="pending-button"]')).toBeVisible()
  }

  async expectBooksVisible() {
    await expect(this.page.locator('[data-testid="profile-books"]')).toBeVisible()
  }

  async expectBooksHidden() {
    await expect(this.page.locator('[data-testid="private-profile-message"]')).toBeVisible()
  }

  async goToFollowRequests() {
    // Click on the follow requests indicator in the header
    await this.page.locator('[data-testid="follow-requests-indicator"]').click()
  }

  async acceptFollowRequest(index: number = 0) {
    await this.page.locator('[data-testid="accept-follow-btn"]').nth(index).click()
    await this.page.waitForTimeout(1000)
  }

  async rejectFollowRequest(index: number = 0) {
    await this.page.locator('[data-testid="reject-follow-btn"]').nth(index).click()
    await this.page.waitForTimeout(1000)
  }

  async setPrivateProfile(isPrivate: boolean) {
    // Navigate to settings/profile
    await this.page.goto('/settings/profile')

    const toggle = this.page.locator('[data-testid="privacy-toggle"]')
    const isChecked = await toggle.isChecked()

    if (isPrivate !== isChecked) {
      await toggle.click()
    }

    await this.page.locator('[data-testid="save-profile-btn"]').click()
    await this.page.waitForTimeout(1000)
  }
}
