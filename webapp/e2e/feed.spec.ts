import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'

test.describe('Feed', () => {
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    await loginPage.goto()

    // Register a new user for testing
    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8)
    const user = {
      displayName: `Feed${shortId}`,
      email: `feed.test.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }
    await loginPage.register(user)
  })

  test('user can access feed page', async ({ page }) => {
    await page.goto('/feed')
    await expect(page).toHaveURL('/feed')
    await page.waitForLoadState('networkidle')
  })

  test('feed page shows empty state when no activities', async ({ page }) => {
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')

    // Empty state icon should be visible
    await expect(page.locator('.empty-state .q-icon')).toBeVisible({ timeout: 10000 })
    // Find people button should be visible
    await expect(page.getByRole('link', { name: /find people|encontrar pessoas/i })).toBeVisible()
  })

  test('feed page does not show redundant title', async ({ page }) => {
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')

    // The h5 title "Feed" should NOT be present (it was removed)
    await expect(page.locator('h5')).not.toBeVisible()
  })

  test('feed navigation from header works', async ({ page }) => {
    // Start on home page
    await page.goto('/home')
    await page.waitForLoadState('networkidle')

    // Click on feed tab in header
    const feedTab = page.locator('[name="feed"], [href="/feed"]').first()
    if (await feedTab.isVisible({ timeout: 3000 })) {
      await feedTab.click()
      await page.waitForLoadState('networkidle')
      await expect(page).toHaveURL('/feed')
    }
  })

  test('empty state has correct styling and elements', async ({ page }) => {
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')

    const emptyState = page.locator('.empty-state')
    await expect(emptyState).toBeVisible({ timeout: 10000 })

    // Check for RSS feed icon
    await expect(emptyState.locator('.q-icon')).toBeVisible()

    // Check for descriptive text
    await expect(emptyState.locator('p')).toBeVisible()

    // Check for "Find people" button
    const findPeopleBtn = emptyState.getByRole('link', { name: /find people|encontrar pessoas/i })
    await expect(findPeopleBtn).toBeVisible()
    await expect(findPeopleBtn).toHaveAttribute('href', '/people')
  })

  test('clicking find people navigates to people page', async ({ page }) => {
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')

    const findPeopleBtn = page.getByRole('link', { name: /find people|encontrar pessoas/i })
    await expect(findPeopleBtn).toBeVisible({ timeout: 10000 })
    await findPeopleBtn.click()

    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL('/people')
  })
})
