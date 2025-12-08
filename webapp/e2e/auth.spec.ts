import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'

test.describe('Authentication', () => {
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    await loginPage.goto()
  })

  test('user can register with valid data', async () => {
    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8) // Keep username under 20 chars
    const user = {
      displayName: `Reg${shortId}`,
      email: `register.test.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }

    await loginPage.register(user)
    await loginPage.expectToBeLoggedIn()
  })

  test('user can login with valid credentials', async ({ page }) => {
    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8) // Keep username under 20 chars
    // First register a user
    const user = {
      displayName: `Log${shortId}`,
      email: `login.test.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }
    await loginPage.register(user)

    // Logout via settings/account
    await page.goto('/settings/account')
    await page.locator('[data-testid="logout-button"]').click()

    // Go back to landing page and login again
    await loginPage.goto()
    await loginPage.login(user.email, user.password)
    await loginPage.expectToBeLoggedIn()
  })

  test('login fails with invalid credentials', async () => {
    await loginPage.login('invalid@email.com', 'WrongPassword123!')
    await loginPage.expectToBeOnLoginPage()
  })

  test('user can logout', async ({ page }) => {
    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8) // Keep username under 20 chars
    const user = {
      displayName: `Out${shortId}`,
      email: `logout.test.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }
    await loginPage.register(user)

    // Logout via settings/account
    await page.goto('/settings/account')
    await page.locator('[data-testid="logout-button"]').click()

    // Should be redirected to landing page
    await expect(page).toHaveURL('/')
  })

  test('registration fails with duplicate email', async ({ page }) => {
    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8) // Keep username under 20 chars
    const user = {
      displayName: `Dup${shortId}`,
      email: `duplicate.test.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }

    // Register first time
    await loginPage.register(user)
    await loginPage.expectToBeLoggedIn()

    // Logout via settings/account
    await page.goto('/settings/account')
    await page.locator('[data-testid="logout-button"]').click()

    // Try to register again with same email but different display name
    await loginPage.goto()
    await loginPage.attemptRegister({
      displayName: `Dup2${shortId}`,
      email: user.email, // Same email
      password: 'TestPassword123!'
    })

    // Wait a bit for the API response and error handling
    await new Promise((resolve) => setTimeout(resolve, 2000))

    // Should stay on the page (modal stays open with error)
    await loginPage.expectToBeOnLoginPage()
  })
})
