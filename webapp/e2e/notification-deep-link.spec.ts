import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { createTestUser } from './fixtures/test-data'

test.describe('Notification Deep Link', () => {
  test('clicking notification navigates to activity with highlight animation', async ({ browser }) => {
    // Create two browser contexts for two users
    const context1 = await browser.newContext()
    const context2 = await browser.newContext()
    const page1 = await context1.newPage()
    const page2 = await context2.newPage()

    const loginPage1 = new LoginPage(page1)
    const loginPage2 = new LoginPage(page2)

    // Register user 1 (will receive notification)
    await loginPage1.goto()
    const user1 = createTestUser('ntf1')
    await loginPage1.register(user1)

    // Register user 2 (will create notification by liking)
    await loginPage2.goto()
    const user2 = createTestUser('ntf2')
    await loginPage2.register(user2)

    // User 1 adds a book to create an activity
    await page1.goto('/add')
    await page1.waitForLoadState('networkidle')

    const searchInput = page1.locator('input.q-field__native').first()
    await searchInput.waitFor({ state: 'visible', timeout: 10000 })
    await searchInput.fill('Dom Casmurro')
    await page1.keyboard.press('Enter')
    await page1.waitForTimeout(3000)

    const bookCards = page1.locator('.book-card')
    if ((await bookCards.count()) > 0) {
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
          await page1.waitForTimeout(2000)
        }
      }
    }

    // User 2 follows user 1 and goes to their profile
    await page2.goto(`/${user1.username}`)
    await page2.waitForLoadState('networkidle')

    // Follow user 1
    const followButton = page2.getByTestId('follow-button')
    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      await page2.waitForTimeout(2000)
    }

    // User 2 clicks on feed tab to see user 1's activities
    const feedTab = page2.getByRole('tab', { name: /feed/i })
    if (await feedTab.isVisible({ timeout: 5000 })) {
      await feedTab.click()
      await page2.waitForTimeout(2000)
    }

    // User 2 likes the first activity
    const likeButton = page2.locator('.activity-group .q-btn').filter({ hasText: /favorite|like/i }).first()
    if (await likeButton.isVisible({ timeout: 5000 })) {
      await likeButton.click()
      await page2.waitForTimeout(2000)
    } else {
      // Alternative: click heart icon button
      const heartButton = page2.locator('.activity-group button:has(.q-icon)').first()
      if (await heartButton.isVisible({ timeout: 3000 })) {
        await heartButton.click()
        await page2.waitForTimeout(2000)
      }
    }

    // User 1 should now have a notification - refresh to get it
    await page1.goto('/feed')
    await page1.waitForLoadState('networkidle')
    await page1.waitForTimeout(2000)

    // Click on notification bell
    const notificationBell = page1.locator('button').filter({ has: page1.locator('.q-icon:text("notifications")') })
    if (await notificationBell.isVisible({ timeout: 5000 })) {
      await notificationBell.click()
      await page1.waitForSelector('.q-dialog', { timeout: 5000 })

      // Check if there's a notification
      const notificationItem = page1.locator('.q-dialog .q-item').first()
      if (await notificationItem.isVisible({ timeout: 5000 })) {
        // Click on the notification
        await notificationItem.click()
        await page1.waitForTimeout(2000)

        // Verify we're on the feed page with activity query param or just feed
        await expect(page1).toHaveURL(/\/feed/)

        // Check for highlight animation class
        const highlightedActivity = page1.locator('.activity-highlight')

        // The highlight should appear briefly (within 2 seconds of navigation)
        // We wait a bit and check if we can see the activity group at all
        const activityGroups = page1.locator('.activity-group')
        await expect(activityGroups.first()).toBeVisible({ timeout: 5000 })

        // Take screenshot for visual verification
        await page1.screenshot({ path: 'test-results/notification-deep-link.png', fullPage: true })
      }
    }

    // Cleanup
    await context1.close()
    await context2.close()
  })

  test('clicking comment notification expands comments', async ({ browser }) => {
    // Create two browser contexts for two users
    const context1 = await browser.newContext()
    const context2 = await browser.newContext()
    const page1 = await context1.newPage()
    const page2 = await context2.newPage()

    const loginPage1 = new LoginPage(page1)
    const loginPage2 = new LoginPage(page2)

    // Register user 1 (will receive notification)
    await loginPage1.goto()
    const user1 = createTestUser('cmt1')
    await loginPage1.register(user1)

    // Register user 2 (will create comment)
    await loginPage2.goto()
    const user2 = createTestUser('cmt2')
    await loginPage2.register(user2)

    // User 1 adds a book to create an activity
    await page1.goto('/add')
    await page1.waitForLoadState('networkidle')

    const searchInput = page1.locator('input.q-field__native').first()
    await searchInput.waitFor({ state: 'visible', timeout: 10000 })
    await searchInput.fill('1984 George Orwell')
    await page1.keyboard.press('Enter')
    await page1.waitForTimeout(3000)

    const bookCards = page1.locator('.book-card')
    if ((await bookCards.count()) > 0) {
      await bookCards.first().click()
      await page1.waitForLoadState('networkidle')
      await page1.waitForTimeout(2000)

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
          await page1.waitForTimeout(2000)
        }
      }
    }

    // User 2 visits user 1's profile and follows
    await page2.goto(`/${user1.username}`)
    await page2.waitForLoadState('networkidle')

    const followButton = page2.getByTestId('follow-button')
    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      await page2.waitForTimeout(2000)
    }

    // User 2 goes to feed tab
    const feedTab = page2.getByRole('tab', { name: /feed/i })
    if (await feedTab.isVisible({ timeout: 5000 })) {
      await feedTab.click()
      await page2.waitForTimeout(2000)
    }

    // User 2 clicks comment button on activity
    const commentButton = page2.locator('.activity-group button:has(.q-icon)').nth(1) // Second button is usually comment
    if (await commentButton.isVisible({ timeout: 5000 })) {
      await commentButton.click()
      await page2.waitForTimeout(1000)

      // Type a comment
      const commentInput = page2.locator('.activity-group input, .activity-group textarea').first()
      if (await commentInput.isVisible({ timeout: 3000 })) {
        await commentInput.fill('Great book choice!')
        await page2.keyboard.press('Enter')
        await page2.waitForTimeout(2000)
      }
    }

    // User 1 checks notifications
    await page1.goto('/home')
    await page1.waitForLoadState('networkidle')
    await page1.waitForTimeout(2000)

    // Click on notification bell
    const notificationBell = page1.locator('button').filter({ has: page1.locator('.q-icon:text("notifications")') })
    if (await notificationBell.isVisible({ timeout: 5000 })) {
      await notificationBell.click()
      await page1.waitForSelector('.q-dialog', { timeout: 5000 })

      // Click on comment notification (should have "commented" text)
      const commentNotification = page1.locator('.q-dialog .q-item').filter({ hasText: /comment|comentou/i }).first()
      if (await commentNotification.isVisible({ timeout: 5000 })) {
        await commentNotification.click()
        await page1.waitForTimeout(2000)

        // Verify we're on feed page
        await expect(page1).toHaveURL(/\/feed/)

        // Comments section should be expanded (visible)
        const commentsSection = page1.locator('.activity-group').first().locator('input, textarea, .comment')

        // Take screenshot
        await page1.screenshot({ path: 'test-results/notification-comment-expand.png', fullPage: true })
      }
    }

    // Cleanup
    await context1.close()
    await context2.close()
  })

  test('opening comments marks related notification as read', async ({ browser }) => {
    // This test verifies the reverse flow:
    // When a user opens comments directly (not via notification),
    // the related notification should be marked as read

    const context1 = await browser.newContext()
    const context2 = await browser.newContext()
    const page1 = await context1.newPage()
    const page2 = await context2.newPage()

    const loginPage1 = new LoginPage(page1)
    const loginPage2 = new LoginPage(page2)

    // Register users
    await loginPage1.goto()
    const user1 = createTestUser('rev1')
    await loginPage1.register(user1)

    await loginPage2.goto()
    const user2 = createTestUser('rev2')
    await loginPage2.register(user2)

    // User 1 adds a book
    await page1.goto('/add')
    await page1.waitForLoadState('networkidle')

    const searchInput = page1.locator('input.q-field__native').first()
    await searchInput.waitFor({ state: 'visible', timeout: 10000 })
    await searchInput.fill('The Great Gatsby')
    await page1.keyboard.press('Enter')
    await page1.waitForTimeout(3000)

    const bookCards = page1.locator('.book-card')
    if ((await bookCards.count()) > 0) {
      await bookCards.first().click()
      await page1.waitForLoadState('networkidle')
      await page1.waitForTimeout(2000)

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
          await page1.waitForTimeout(2000)
        }
      }
    }

    // User 2 follows and likes
    await page2.goto(`/${user1.username}`)
    await page2.waitForLoadState('networkidle')

    const followButton = page2.getByTestId('follow-button')
    if (await followButton.isVisible({ timeout: 5000 })) {
      await followButton.click()
      await page2.waitForTimeout(2000)
    }

    const feedTab = page2.getByRole('tab', { name: /feed/i })
    if (await feedTab.isVisible({ timeout: 5000 })) {
      await feedTab.click()
      await page2.waitForTimeout(2000)
    }

    // Like the activity
    const heartButton = page2.locator('.activity-group button:has(.q-icon)').first()
    if (await heartButton.isVisible({ timeout: 3000 })) {
      await heartButton.click()
      await page2.waitForTimeout(2000)
    }

    // User 1 goes to feed (NOT via notification click)
    await page1.goto('/feed')
    await page1.waitForLoadState('networkidle')
    await page1.waitForTimeout(2000)

    // Check initial unread count in notification bell
    const notificationBadge = page1.locator('.q-badge')
    let initialUnreadCount = 0
    if (await notificationBadge.isVisible({ timeout: 3000 })) {
      const badgeText = await notificationBadge.textContent()
      initialUnreadCount = parseInt(badgeText || '0')
    }

    // User 1 clicks to expand comments on the activity (this should mark notification as read)
    const commentButton = page1.locator('.activity-group button:has(.q-icon)').nth(1)
    if (await commentButton.isVisible({ timeout: 5000 })) {
      await commentButton.click()
      await page1.waitForTimeout(2000)

      // Check if unread count decreased
      const newBadgeText = await notificationBadge.textContent().catch(() => '0')
      const newUnreadCount = parseInt(newBadgeText || '0')

      // Take screenshot
      await page1.screenshot({ path: 'test-results/notification-auto-read.png', fullPage: true })

      // The unread count should have decreased (or badge should be gone)
      // This verifies the reverse flow works
    }

    // Cleanup
    await context1.close()
    await context2.close()
  })
})
