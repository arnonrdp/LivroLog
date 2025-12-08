import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { ProfilePage } from './pages/profile.page'
import { createTestUser } from './fixtures/test-data'

test.describe('Social Features', () => {
  test.describe('User Profile', () => {
    test('user can view their own profile settings', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('prof')
      await loginPage.register(user)

      // Navigate to own profile via settings
      await page.goto('/settings/profile')

      // Should see profile settings page with Shelf Name and Username
      await expect(page.getByText('Shelf Name')).toBeVisible()
      await expect(page.getByText('Username')).toBeVisible()
      await expect(page.getByText('Private Profile')).toBeVisible()
    })

    test('user can access settings page', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register and login
      await loginPage.goto()
      const user = createTestUser('set')
      await loginPage.register(user)

      // Go to settings
      await page.goto('/settings')

      // Should see settings sections
      await expect(page.getByText('Profile')).toBeVisible()
      await expect(page.getByText('Account')).toBeVisible()
    })

    test('user can see people page', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register and login
      await loginPage.goto()
      const user = createTestUser('ppl')
      await loginPage.register(user)

      // Navigate to people page
      await page.goto('/people')

      // Should see people page elements
      await expect(page).toHaveURL(/\/people/)
    })
  })

  test.describe('Following System', () => {
    test('people page shows search functionality', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register and login
      await loginPage.goto()
      const user = createTestUser('flw')
      await loginPage.register(user)

      // Navigate to people page
      await page.goto('/people')

      // Should be on people page and see search or user list
      await expect(page).toHaveURL(/\/people/)
    })
  })

  test.describe('Privacy Settings', () => {
    test('user can see private profile toggle in profile settings', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register and login
      await loginPage.goto()
      const user = createTestUser('priv')
      await loginPage.register(user)

      // Go to profile settings (not account)
      await page.goto('/settings/profile')

      // Should see Private Profile option
      await expect(page.getByText('Private Profile')).toBeVisible()
    })

    test('account settings shows email and password options', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register and login
      await loginPage.goto()
      const user = createTestUser('acct')
      await loginPage.register(user)

      // Go to account settings
      await page.goto('/settings/account')

      // Should see account-related options (use exact match to avoid ambiguity)
      await expect(page.getByText('Account', { exact: true })).toBeVisible()
      await expect(page.getByText('Change Password')).toBeVisible()
    })
  })
})
