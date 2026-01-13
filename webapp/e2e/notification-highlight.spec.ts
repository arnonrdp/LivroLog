import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { createTestUser } from './fixtures/test-data'

test.describe('Notification Highlight Animation', () => {
  test('activity gets highlighted when navigating from notification', async ({ browser }) => {
    // Create two browser contexts for two users
    const context1 = await browser.newContext()
    const context2 = await browser.newContext()
    const page1 = await context1.newPage()
    const page2 = await context2.newPage()

    const loginPage1 = new LoginPage(page1)
    const loginPage2 = new LoginPage(page2)

    // Register user 1 (will receive notification)
    await loginPage1.goto()
    const user1 = createTestUser('hl1')
    await loginPage1.register(user1)
    console.log('User 1 registered:', user1.username)

    // Register user 2 (will like user1's activity)
    await loginPage2.goto()
    const user2 = createTestUser('hl2')
    await loginPage2.register(user2)
    console.log('User 2 registered:', user2.username)

    // User 1 adds a book to create an activity
    await page1.goto('/add')
    await page1.waitForLoadState('networkidle')

    const searchInput = page1.locator('input.q-field__native').first()
    await searchInput.waitFor({ state: 'visible', timeout: 10000 })
    await searchInput.fill('Dom Casmurro')
    await page1.keyboard.press('Enter')
    await page1.waitForTimeout(3000)

    // Click on first book result
    const bookCards = page1.locator('.book-card')
    const bookCount = await bookCards.count()
    console.log('Found', bookCount, 'books')

    if (bookCount > 0) {
      await bookCards.first().click()
      await page1.waitForLoadState('networkidle')
      await page1.waitForTimeout(2000)

      // Add book to shelf
      const addToShelfDropdown = page1
        .locator('.q-btn-dropdown')
        .filter({ hasText: /add to shelf|adicionar/i })
        .first()

      if (await addToShelfDropdown.isVisible({ timeout: 5000 })) {
        await addToShelfDropdown.click()
        await page1.waitForTimeout(1000)
        const dropdownItem = page1.locator('.q-menu .q-item').first()
        if (await dropdownItem.isVisible({ timeout: 2000 })) {
          await dropdownItem.click()
          console.log('Book added to shelf')
          await page1.waitForTimeout(2000)
        }
      }
    }

    // User 2 follows user 1
    await page2.goto(`/${user1.username}`)
    await page2.waitForLoadState('networkidle')
    console.log('User 2 visiting user 1 profile')

    const followButton = page2.getByTestId('follow-button')
    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      console.log('User 2 followed user 1')
      await page2.waitForTimeout(2000)
    }

    // User 2 goes to feed tab on user 1's profile
    const feedTab = page2.getByRole('tab', { name: /feed/i })
    if (await feedTab.isVisible({ timeout: 5000 })) {
      await feedTab.click()
      await page2.waitForTimeout(2000)
      console.log('User 2 viewing user 1 feed tab')
    }

    // User 2 likes the activity (first heart button)
    const activityGroup = page2.locator('.activity-group').first()
    if (await activityGroup.isVisible({ timeout: 5000 })) {
      // Find heart/like button
      const likeBtn = activityGroup.locator('button').first()
      await likeBtn.click()
      console.log('User 2 liked activity')
      await page2.waitForTimeout(2000)
    }

    // Now user 1 should have a notification
    // Go to home first to refresh
    await page1.goto('/home')
    await page1.waitForLoadState('networkidle')
    await page1.waitForTimeout(2000)

    // Take screenshot before clicking notification
    await page1.screenshot({ path: 'test-results/before-notification-click.png', fullPage: true })

    // Click notification bell
    const bellButton = page1.locator('button:has(.q-icon)').filter({ hasText: '' }).first()
    const buttons = page1.locator('button')
    for (let i = 0; i < await buttons.count(); i++) {
      const btn = buttons.nth(i)
      const icon = btn.locator('.q-icon')
      if (await icon.count() > 0) {
        const iconText = await icon.textContent()
        if (iconText?.includes('notifications')) {
          await btn.click()
          console.log('Clicked notification bell')
          break
        }
      }
    }

    await page1.waitForTimeout(1000)

    // Check if dialog opened
    const dialog = page1.locator('.q-dialog')
    if (await dialog.isVisible({ timeout: 3000 })) {
      await page1.screenshot({ path: 'test-results/notification-dialog.png', fullPage: true })

      // Click first notification
      const notificationItem = dialog.locator('.q-item').first()
      if (await notificationItem.isVisible({ timeout: 3000 })) {
        console.log('Clicking on notification')
        await notificationItem.click()
        await page1.waitForTimeout(500) // Wait for navigation to start

        // Wait for feed page
        await page1.waitForURL(/\/feed/, { timeout: 10000 })
        console.log('Navigated to feed')

        // Wait for scroll and highlight animation
        await page1.waitForTimeout(1500) // Wait for scroll (500ms) + part of animation

        // Take screenshot during highlight
        await page1.screenshot({ path: 'test-results/during-highlight.png', fullPage: true })
        console.log('Screenshot taken during highlight')

        // Check if highlight class exists on any activity
        const highlightedActivity = page1.locator('.activity-highlight')
        const hasHighlight = await highlightedActivity.count() > 0
        console.log('Has highlight class:', hasHighlight)

        // Wait for animation to complete
        await page1.waitForTimeout(2000)

        // Take screenshot after highlight
        await page1.screenshot({ path: 'test-results/after-highlight.png', fullPage: true })

        // Verify we're on feed page
        await expect(page1).toHaveURL(/\/feed/)
      }
    }

    // Cleanup
    await context1.close()
    await context2.close()
  })
})
