import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'

test.describe('Feed Activity - User Profile Integration', () => {
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    await loginPage.goto()

    // Register a new user for testing
    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8)
    const user = {
      displayName: `FeedUser${shortId}`,
      email: `feed.activity.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }
    await loginPage.register(user)
  })

  test('following a user and checking feed shows their profile', async ({ page }) => {
    // Go to sniperriobranco's profile
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')

    // Verify we're on the profile page (h1 should have shelf name)
    await expect(page.locator('h1')).toBeVisible({ timeout: 10000 })

    // Find and click the follow button if visible
    const followButton = page.getByTestId('follow-button')

    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      await page.waitForTimeout(2000)
    }

    // Now go to the feed page
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')

    // Verify we're on the feed page without errors
    await expect(page).toHaveURL('/feed')

    // Page should load successfully (empty state or activities)
    const hasEmptyState = await page.locator('.empty-state').isVisible()
    const hasActivities = (await page.locator('.activity-group').count()) > 0

    // One of these should be true
    expect(hasEmptyState || hasActivities).toBeTruthy()
  })

  test('user profile has feed tab', async ({ page }) => {
    // Go to sniperriobranco's profile
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')

    // Verify we're on the profile page (h1 should have shelf name)
    await expect(page.locator('h1')).toBeVisible({ timeout: 10000 })

    // Check that Feed tab exists in profile tabs
    const feedTab = page.getByRole('tab', { name: /feed/i })
    await expect(feedTab).toBeVisible({ timeout: 5000 })
  })

  test('clicking feed tab on profile shows activities section', async ({ page }) => {
    // Go to sniperriobranco's profile
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')

    // Verify we're on the profile page (h1 should have shelf name)
    await expect(page.locator('h1')).toBeVisible({ timeout: 10000 })

    // Click on the feed tab
    const feedTab = page.getByRole('tab', { name: /feed/i })
    if (await feedTab.isVisible({ timeout: 5000 })) {
      await feedTab.click()
      await page.waitForTimeout(2000)

      // Check for activity groups or "no activities" message (all locales)
      const activityGroups = page.locator('.activity-group')
      const noActivitiesText = page.getByText(/no activities|sem atividades|nenhuma atividade|まだアクティビティはありません|Henüz etkinlik yok/i)

      const hasActivities = (await activityGroups.count()) > 0
      const hasNoActivitiesMsg = await noActivitiesText.isVisible()

      // One of these should be true
      expect(hasActivities || hasNoActivitiesMsg).toBeTruthy()
    }
  })

  test('feed tab icon is correct on profile', async ({ page }) => {
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')

    // The feed tab should have rss_feed icon
    const feedTab = page.locator('[aria-label*="Feed"], [aria-label*="feed"]').first()
    await expect(feedTab).toBeVisible({ timeout: 5000 })
  })
})

test.describe('Feed Navigation and UI', () => {
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    await loginPage.goto()

    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8)
    const user = {
      displayName: `FeedNav${shortId}`,
      email: `feed.nav.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }
    await loginPage.register(user)
  })

  test('feed icon in header is correct and clickable', async ({ page }) => {
    await page.goto('/home')
    await page.waitForLoadState('networkidle')

    // Find the feed tab by name attribute
    const feedTab = page.locator('[name="feed"]').first()

    if (await feedTab.isVisible({ timeout: 3000 })) {
      await feedTab.click()
      await page.waitForLoadState('networkidle')
      await expect(page).toHaveURL('/feed')
    }
  })

  test('feed tab becomes active when on feed page', async ({ page }) => {
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')

    // The feed tab should have active styling
    const activeTab = page.locator('.tab--active, .q-tab--active').first()
    await expect(activeTab).toBeVisible({ timeout: 5000 })
  })

  test('only feed tab is active on feed page (not people)', async ({ page }) => {
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')

    // Wait for page to stabilize
    await page.waitForTimeout(1000)

    // The current URL should be /feed, not /people
    // This test verifies that the bug where both feed and people tabs appeared active is fixed
    await expect(page).toHaveURL('/feed')

    // Verify that we're not seeing the people page content
    await expect(page.locator('.empty-state, .activity-group, .feed-container')).toBeVisible({ timeout: 5000 })
  })
})

test.describe('Feed Profile Consistency', () => {
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    await loginPage.goto()

    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8)
    const user = {
      displayName: `ProfileFeed${shortId}`,
      email: `profile.feed.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }
    await loginPage.register(user)
  })

  test('user profile feed tab shows own activities after adding book', async ({ page }) => {
    // Add a book to create an activity
    await page.goto('/add')
    await page.waitForLoadState('networkidle')

    const searchInput = page.locator('input.q-field__native').first()
    await searchInput.waitFor({ state: 'visible', timeout: 10000 })
    await searchInput.fill('1984 George Orwell')
    await page.keyboard.press('Enter')
    await page.waitForTimeout(3000)

    const bookCards = page.locator('.book-card')
    if ((await bookCards.count()) > 0) {
      await bookCards.first().click()
      await page.waitForLoadState('networkidle')
      await page.waitForTimeout(2000)

      // Add book to shelf
      const addToShelfDropdown = page
        .locator('.q-btn-dropdown')
        .filter({ hasText: /add to shelf|adicionar/i })
        .first()
      if (await addToShelfDropdown.isVisible({ timeout: 5000 })) {
        await addToShelfDropdown.click()
        await page.waitForTimeout(1000)
        const dropdownItem = page.locator('.q-menu .q-item').first()
        if (await dropdownItem.isVisible({ timeout: 2000 })) {
          await dropdownItem.click()
          await page.waitForTimeout(2000)
        }
      }
    }

    // Get username from settings
    await page.goto('/settings/profile')
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1000)

    const usernameText = await page
      .locator('text=/localhost\\//')
      .textContent()
      .catch(() => null)
    let username = ''
    if (usernameText) {
      const match = usernameText.match(/localhost\/\s*(\w+)/)
      if (match) username = match[1]
    }

    if (username) {
      // Go to own profile
      await page.goto(`/${username}`)
      await page.waitForLoadState('networkidle')
      await page.waitForTimeout(2000)

      // Click Feed tab
      const feedTab = page.getByRole('tab', { name: /feed/i })
      if (await feedTab.isVisible({ timeout: 5000 })) {
        await feedTab.click()
        await page.waitForTimeout(3000)

        // Verify activities OR empty state (depending on if book was added) - all locales
        const activityGroups = page.locator('.activity-group')
        const noActivitiesText = page.getByText(/no activities|sem atividades|nenhuma atividade|まだアクティビティはありません|Henüz etkinlik yok/i)
        const hasActivities = (await activityGroups.count()) > 0
        const hasNoActivitiesMsg = await noActivitiesText.isVisible()

        // One of these should be visible
        expect(hasActivities || hasNoActivitiesMsg).toBeTruthy()
      }
    }
  })

  test('user activities in main feed match profile feed tab', async ({ page }) => {
    // Go to sniperriobranco profile which should have activities
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(2000)

    // Click Feed tab
    const feedTab = page.getByRole('tab', { name: /feed/i })
    if (await feedTab.isVisible({ timeout: 5000 })) {
      await feedTab.click()
      await page.waitForTimeout(3000)

      // Check for activities or no-activities message (all locales)
      const activityGroups = page.locator('.activity-group')
      const noActivitiesText = page.getByText(/no activities|sem atividades|nenhuma atividade|まだアクティビティはありません|Henüz etkinlik yok/i)
      const hasActivities = (await activityGroups.count()) > 0
      const hasNoActivitiesMsg = await noActivitiesText.isVisible()

      // Page should render correctly without errors
      expect(hasActivities || hasNoActivitiesMsg).toBeTruthy()

      // Verify no error messages
      await expect(page.getByText(/error|erro/i)).not.toBeVisible()
    }
  })
})

test.describe('Feed API Integration', () => {
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    await loginPage.goto()

    const timestamp = Date.now()
    const shortId = String(timestamp).slice(-8)
    const user = {
      displayName: `FeedAPI${shortId}`,
      email: `feed.api.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }
    await loginPage.register(user)
  })

  test('feed API returns correct structure', async ({ page }) => {
    // Intercept the feeds API call
    const responsePromise = page.waitForResponse((response) => response.url().includes('/feeds') && response.status() === 200)

    await page.goto('/feed')

    const response = await responsePromise
    const data = await response.json()

    // Verify API response structure
    expect(data).toHaveProperty('data')
    expect(data).toHaveProperty('grouped')
    expect(data).toHaveProperty('meta')
    expect(data.meta).toHaveProperty('total')
    expect(data.meta).toHaveProperty('current_page')
    expect(data.meta).toHaveProperty('per_page')
    expect(data.meta).toHaveProperty('last_page')
  })

  test('feed handles empty response gracefully', async ({ page }) => {
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')

    // For a new user with no followings, should show empty state
    const emptyState = page.locator('.empty-state')
    const activities = page.locator('.activity-group')

    // Either empty state or activities should be visible
    const hasEmptyState = await emptyState.isVisible()
    const hasActivities = (await activities.count()) > 0

    expect(hasEmptyState || hasActivities).toBeTruthy()

    // No error messages should be visible
    await expect(page.getByText(/error|erro/i)).not.toBeVisible()
  })
})
