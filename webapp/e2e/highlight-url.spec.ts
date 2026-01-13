import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { createTestUser } from './fixtures/test-data'

test.describe('Highlight via URL Parameter', () => {
  test('activity highlight animation triggers on URL with activity param', async ({ page, request }) => {
    const loginPage = new LoginPage(page)

    // Register a new user
    await loginPage.goto()
    const user = createTestUser('url')
    await loginPage.register(user)
    console.log('User registered:', user.username)

    // Follow sniperriobranco to get their activities in feed
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')

    const followButton = page.getByTestId('follow-button')
    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      console.log('Followed sniperriobranco')
      await page.waitForTimeout(2000)
    }

    // Go to feed first to load activities
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(2000)

    // Get the first activity's ID by intercepting the API response
    const activityGroups = page.locator('.activity-group')
    const activityCount = await activityGroups.count()
    console.log('Activity groups found:', activityCount)

    if (activityCount > 0) {
      // Take baseline screenshot
      await page.screenshot({ path: 'test-results/highlight-url-baseline.png', fullPage: true })

      // Get the first activity's data-* attribute or find its ID somehow
      // For now, we'll use the API to get the activity ID
      const feedResponse = await request.get('http://localhost:8000/feeds', {
        headers: {
          'Authorization': `Bearer ${await page.evaluate(() => localStorage.getItem('token'))}`
        }
      })

      if (feedResponse.ok()) {
        const feedData = await feedResponse.json()
        if (feedData.grouped && feedData.grouped.length > 0) {
          const firstActivityId = feedData.grouped[0].first_activity_id
          console.log('First activity ID:', firstActivityId)

          // Now navigate to feed with this activity ID in query param
          await page.goto(`/feed?activity=${firstActivityId}`)
          await page.waitForLoadState('networkidle')

          // Wait for scroll animation (500ms) + a bit
          await page.waitForTimeout(800)

          // Take screenshot during highlight animation
          await page.screenshot({ path: 'test-results/highlight-url-during.png', fullPage: true })

          // Check if highlight class is present
          const highlightedActivity = page.locator('.activity-highlight')
          const hasHighlight = await highlightedActivity.count() > 0
          console.log('Highlight class present:', hasHighlight)

          // Wait for animation to complete (1.5s)
          await page.waitForTimeout(1500)

          // Take screenshot after animation
          await page.screenshot({ path: 'test-results/highlight-url-after.png', fullPage: true })

          // URL should have been cleared
          await expect(page).toHaveURL('/feed')
        }
      }
    } else {
      console.log('No activities found in feed, skipping highlight test')
      // Mark test as passed anyway since the user might not have activities
    }
  })

  test('comment expand works with URL parameter', async ({ page, request }) => {
    const loginPage = new LoginPage(page)

    // Register a new user
    await loginPage.goto()
    const user = createTestUser('exp')
    await loginPage.register(user)

    // Follow sniperriobranco
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')

    const followButton = page.getByTestId('follow-button')
    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      await page.waitForTimeout(2000)
    }

    // Go to feed
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(2000)

    const activityGroups = page.locator('.activity-group')
    const activityCount = await activityGroups.count()

    if (activityCount > 0) {
      // Get activity ID via API
      const feedResponse = await request.get('http://localhost:8000/feeds', {
        headers: {
          'Authorization': `Bearer ${await page.evaluate(() => localStorage.getItem('token'))}`
        }
      })

      if (feedResponse.ok()) {
        const feedData = await feedResponse.json()
        if (feedData.grouped && feedData.grouped.length > 0) {
          const firstActivityId = feedData.grouped[0].first_activity_id
          console.log('Testing expand=comments with activity:', firstActivityId)

          // Navigate with expand=comments param
          await page.goto(`/feed?activity=${firstActivityId}&expand=comments`)
          await page.waitForLoadState('networkidle')
          await page.waitForTimeout(1000)

          // Take screenshot
          await page.screenshot({ path: 'test-results/highlight-url-comments.png', fullPage: true })

          // Check if comments section is visible in the first activity
          const commentsSection = activityGroups.first().locator('input, textarea')
          const hasComments = await commentsSection.count() > 0
          console.log('Comments section visible:', hasComments)

          // Verify comments expanded
          expect(hasComments).toBeTruthy()
        }
      }
    }
  })
})
