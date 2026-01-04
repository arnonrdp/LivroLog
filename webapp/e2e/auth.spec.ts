import { test, expect } from '@playwright/test'
import { faker } from '@faker-js/faker'
import { LoginPage } from './pages/login.page'
import { createTestUser } from './fixtures/test-data'

test.describe('Authentication', () => {
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    await loginPage.goto()
  })

  test('user can register with valid data', async () => {
    const user = createTestUser('reg')
    await loginPage.register(user)
    await loginPage.expectToBeLoggedIn()
  })

  test('user can login with valid credentials', async ({ page }) => {
    const user = createTestUser('log')
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
    const invalidEmail = faker.internet.email()
    await loginPage.login(invalidEmail, 'WrongPassword123!')
    await loginPage.expectToBeOnLoginPage()
  })

  test('user can logout', async ({ page }) => {
    const user = createTestUser('out')
    await loginPage.register(user)

    // Logout via settings/account
    await page.goto('/settings/account')
    await page.locator('[data-testid="logout-button"]').click()

    // Should be redirected to landing page
    await expect(page).toHaveURL('/')
  })

  test('registration fails with duplicate email', async ({ page }) => {
    const user = createTestUser('dup')

    // Register first time
    await loginPage.register(user)
    await loginPage.expectToBeLoggedIn()

    // Logout via settings/account
    await page.goto('/settings/account')
    await page.locator('[data-testid="logout-button"]').click()

    // Try to register again with same email but different display name
    await loginPage.goto()
    const duplicateUser = createTestUser('dup2')
    await loginPage.attemptRegister({
      displayName: duplicateUser.displayName,
      email: user.email, // Same email as first user
      password: 'TestPassword123!'
    })

    // Wait a bit for the API response and error handling
    await new Promise((resolve) => setTimeout(resolve, 2000))

    // Should stay on the page (modal stays open with error)
    await loginPage.expectToBeOnLoginPage()
  })
})
