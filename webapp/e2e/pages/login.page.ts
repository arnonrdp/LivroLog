import { Page, expect } from '@playwright/test'

export class LoginPage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/')
  }

  async login(email: string, password: string) {
    // Click Sign In button in header to open modal
    await this.page.locator('[data-testid="header-signin-btn"]').click()
    // Wait for modal to appear
    await this.page.waitForSelector('[data-testid="signin-tab"]')
    await this.page.locator('[data-testid="signin-tab"]').click()
    await this.page.locator('[data-testid="email"]').fill(email)
    await this.page.locator('[data-testid="password"]').fill(password)
    await this.page.locator('[data-testid="login-button"]').click()
  }

  async register(data: { displayName: string; email: string; username?: string; password: string }) {
    // Click Sign Up button in header to open modal
    await this.page.locator('[data-testid="header-signup-btn"]').click()
    // Wait for modal to appear
    await this.page.waitForSelector('[data-testid="signup-tab"]')
    await this.page.locator('[data-testid="signup-tab"]').click()
    await this.page.locator('[data-testid="display-name"]').fill(data.displayName)
    await this.page.locator('[data-testid="email"]').fill(data.email)
    await this.page.locator('[data-testid="password"]').fill(data.password)
    await this.page.locator('[data-testid="password-confirmation"]').fill(data.password)
    await this.page.locator('[data-testid="register-button"]').click()
    // Wait for registration to complete and redirect (increased timeout for API calls)
    await this.page.waitForURL(/\/home/, { timeout: 30000 })
  }

  async attemptRegister(data: { displayName: string; email: string; password: string }) {
    // Click Sign Up button in header to open modal
    await this.page.locator('[data-testid="header-signup-btn"]').click()
    // Wait for modal to appear
    await this.page.waitForSelector('[data-testid="signup-tab"]')
    await this.page.locator('[data-testid="signup-tab"]').click()
    await this.page.locator('[data-testid="display-name"]').fill(data.displayName)
    await this.page.locator('[data-testid="email"]').fill(data.email)
    await this.page.locator('[data-testid="password"]').fill(data.password)
    await this.page.locator('[data-testid="password-confirmation"]').fill(data.password)
    await this.page.locator('[data-testid="register-button"]').click()
    // Don't wait for redirect - used when expecting failure
  }

  async expectToBeOnLoginPage() {
    await expect(this.page.locator('[data-testid="header-signin-btn"]')).toBeVisible()
  }

  async expectToBeLoggedIn() {
    await expect(this.page).toHaveURL(/\/home/)
  }

  async expectErrorMessage(message: string) {
    await expect(this.page.locator('.q-notification')).toContainText(message)
  }
}
