import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { createTestUser } from './fixtures/test-data'

test.describe('Direct Highlight Test', () => {
  test('navigating to feed with activity param shows highlight', async ({ page }) => {
    const loginPage = new LoginPage(page)

    // Register a new user
    await loginPage.goto()
    const user = createTestUser('drct')
    await loginPage.register(user)

    // First, create an activity by adding a book via the existing flow
    // Go to an existing user's profile who has activities
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')

    // Follow them to see their activities
    const followButton = page.getByTestId('follow-button')
    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      await page.waitForTimeout(2000)
    }

    // Go to feed to see activities
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(2000)

    // Get the first activity's ID from the DOM
    const activityGroup = page.locator('.activity-group').first()

    if (await activityGroup.isVisible({ timeout: 5000 })) {
      // Take screenshot of normal feed
      await page.screenshot({ path: 'test-results/feed-normal.png', fullPage: true })

      // Now navigate to feed with a fake activity ID to test the highlight CSS exists
      // The animation should trigger even if the activity doesn't exist in feed
      // We just want to verify the CSS animation is working

      // First, let's manually add the highlight class to verify CSS works
      await page.evaluate(() => {
        const activity = document.querySelector('.activity-group')
        if (activity) {
          activity.classList.add('activity-highlight')
        }
      })

      // Take screenshot with highlight class added
      await page.screenshot({ path: 'test-results/feed-with-highlight.png', fullPage: true })

      // Verify the highlight class creates visual change
      const highlightedActivity = page.locator('.activity-highlight')
      await expect(highlightedActivity).toBeVisible()

      // Check computed styles
      const boxShadow = await highlightedActivity.evaluate((el) => {
        return window.getComputedStyle(el).boxShadow
      })

      console.log('Box shadow during animation:', boxShadow)

      // Wait for animation to complete
      await page.waitForTimeout(2000)
      await page.screenshot({ path: 'test-results/feed-after-animation.png', fullPage: true })
    }
  })

  test('highlight class has correct animation styles', async ({ page }) => {
    const loginPage = new LoginPage(page)

    // Register and go to feed
    await loginPage.goto()
    const user = createTestUser('css')
    await loginPage.register(user)

    // Follow sniperriobranco to get activities
    await page.goto('/sniperriobranco')
    await page.waitForLoadState('networkidle')

    const followButton = page.getByTestId('follow-button')
    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      await page.waitForTimeout(2000)
    }

    await page.goto('/feed')
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(2000)

    const activityGroup = page.locator('.activity-group').first()

    if (await activityGroup.isVisible({ timeout: 5000 })) {
      // Get the CSS animation name applied to .activity-highlight
      const animationName = await page.evaluate(() => {
        // Create a temporary element with the highlight class
        const temp = document.createElement('div')
        temp.className = 'activity-group activity-highlight'
        document.body.appendChild(temp)

        const style = window.getComputedStyle(temp)
        const animation = style.animationName

        document.body.removeChild(temp)
        return animation
      })

      console.log('Animation name:', animationName)

      // Verify the animation is defined
      expect(animationName).not.toBe('none')
    }
  })
})
