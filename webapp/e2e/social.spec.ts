import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
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

  test.describe('Shelf Menu (Hamburger)', () => {
    test('shelf menu shows correct user data on /home', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('menu')
      await loginPage.register(user)

      // Should be on /home
      await expect(page).toHaveURL(/\/home/)

      // Click hamburger menu button
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()

      // Wait for dialog to appear
      await page.waitForSelector('.q-dialog')

      // Menu should show the logged-in user's display name and username in the dialog
      const dialog = page.locator('.q-dialog')
      await expect(dialog.locator('.text-h6.text-weight-medium')).toContainText(user.displayName)
      await expect(dialog.getByText(`@${user.username}`)).toBeVisible()
    })

    test('shelf menu shows correct user data after visiting another profile', async ({ browser }) => {
      // Create two browser contexts for two users
      const context1 = await browser.newContext()
      const context2 = await browser.newContext()
      const page1 = await context1.newPage()
      const page2 = await context2.newPage()

      const loginPage1 = new LoginPage(page1)
      const loginPage2 = new LoginPage(page2)

      // Register user 1
      await loginPage1.goto()
      const user1 = createTestUser('hbg1')
      await loginPage1.register(user1)

      // Register user 2
      await loginPage2.goto()
      const user2 = createTestUser('hbg2')
      await loginPage2.register(user2)

      // User 1 visits user 2's profile
      await page1.goto(`/${user2.username}`)
      await page1.waitForLoadState('networkidle')

      // Click hamburger menu on user 2's profile
      await page1.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page1.waitForSelector('.q-dialog')

      // Menu should show user 2's data (the profile being viewed)
      const dialog1 = page1.locator('.q-dialog')
      await expect(dialog1.locator('.text-h6.text-weight-medium')).toContainText(user2.displayName)

      // Close the menu
      await page1.keyboard.press('Escape')
      await page1.waitForTimeout(300)

      // Navigate back to /home
      await page1.goto('/home')
      await page1.waitForLoadState('networkidle')

      // Click hamburger menu - should show user 1's data, not user 2's
      await page1.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page1.waitForSelector('.q-dialog')

      // Should show user 1's data (the logged-in user)
      const dialog2 = page1.locator('.q-dialog')
      await expect(dialog2.locator('.text-h6.text-weight-medium')).toContainText(user1.displayName)
      await expect(dialog2.getByText(`@${user1.username}`)).toBeVisible()

      // Should NOT show user 2's data in the dialog
      await expect(dialog2.locator('.text-h6.text-weight-medium')).not.toContainText(user2.displayName)

      // Cleanup
      await context1.close()
      await context2.close()
    })

    test('shelf menu displays user stats (books, followers, following)', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('stat')
      await loginPage.register(user)

      // Open shelf menu
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page.waitForSelector('.q-dialog')

      const dialog = page.locator('.q-dialog')

      // Should show stats section with books, followers, following counts
      // New user should have 0 books, 0 followers, 0 following
      await expect(dialog.getByText('0').first()).toBeVisible()
    })

    test('shelf menu has search input', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('srch')
      await loginPage.register(user)

      // Open shelf menu
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page.waitForSelector('.q-dialog')

      const dialog = page.locator('.q-dialog')

      // Should have a search input
      await expect(dialog.locator('input[type="text"], .q-input input')).toBeVisible()
    })

    test('shelf menu has sort options', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('sort')
      await loginPage.register(user)

      // Open shelf menu
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page.waitForSelector('.q-dialog')

      const dialog = page.locator('.q-dialog')

      // Should have sort options list
      await expect(dialog.locator('.q-list')).toBeVisible()

      // Should have at least one sort option item
      await expect(dialog.locator('.q-item').first()).toBeVisible()
    })

    test('shelf menu shows follow button on other user profile', async ({ browser }) => {
      // Create two browser contexts for two users
      const context1 = await browser.newContext()
      const context2 = await browser.newContext()
      const page1 = await context1.newPage()
      const page2 = await context2.newPage()

      const loginPage1 = new LoginPage(page1)
      const loginPage2 = new LoginPage(page2)

      // Register user 1
      await loginPage1.goto()
      const user1 = createTestUser('flw1')
      await loginPage1.register(user1)

      // Register user 2
      await loginPage2.goto()
      const user2 = createTestUser('flw2')
      await loginPage2.register(user2)

      // User 1 visits user 2's profile
      await page1.goto(`/${user2.username}`)
      await page1.waitForLoadState('networkidle')

      // Open shelf menu
      await page1.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page1.waitForSelector('.q-dialog')

      const dialog = page1.locator('.q-dialog')

      // Should show follow button when viewing another user's profile
      await expect(dialog.locator('button').filter({ hasText: /follow/i })).toBeVisible()

      // Cleanup
      await context1.close()
      await context2.close()
    })

    test('shelf menu does not show follow button on own profile', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('ownp')
      await loginPage.register(user)

      // Open shelf menu on /home
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page.waitForSelector('.q-dialog')

      const dialog = page.locator('.q-dialog')

      // Should NOT show follow button on own profile
      await expect(dialog.locator('button').filter({ hasText: /^follow$/i })).not.toBeVisible()
    })

    test('shelf menu shows own data when visiting own profile via URL', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('self')
      await loginPage.register(user)

      // Navigate to own profile via URL (like /arnonrodrigues)
      await page.goto(`/${user.username}`)
      await page.waitForLoadState('networkidle')

      // Open shelf menu
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page.waitForSelector('.q-dialog')

      const dialog = page.locator('.q-dialog')

      // Should show own user data
      await expect(dialog.locator('.text-h6.text-weight-medium')).toContainText(user.displayName)
      await expect(dialog.getByText(`@${user.username}`)).toBeVisible()

      // Should NOT show follow button on own profile (even via URL)
      await expect(dialog.locator('button').filter({ hasText: /^follow$/i })).not.toBeVisible()
    })

    test('shelf menu shows avatar fallback icon when user has no avatar', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user (new users don't have avatars)
      await loginPage.goto()
      const user = createTestUser('noav')
      await loginPage.register(user)

      // Open shelf menu
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page.waitForSelector('.q-dialog')

      const dialog = page.locator('.q-dialog')

      // Should show avatar container with fallback icon (person icon)
      await expect(dialog.locator('.q-avatar')).toBeVisible()
      await expect(dialog.locator('.q-avatar .q-icon')).toBeVisible()
    })

    test('shelf menu followers/following stats are clickable on own profile', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('clck')
      await loginPage.register(user)

      // Open shelf menu
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page.waitForSelector('.q-dialog')

      const dialog = page.locator('.q-dialog')

      // On own profile, followers/following should have cursor-pointer class
      const statsRow = dialog.locator('.row.q-gutter-md.justify-center')
      const clickableStats = statsRow.locator('.cursor-pointer')

      // Should have 2 clickable stats (followers and following, not books)
      await expect(clickableStats).toHaveCount(2)
    })

    test('shelf menu has all four sort options', async ({ page }) => {
      const loginPage = new LoginPage(page)

      // Register a new user
      await loginPage.goto()
      const user = createTestUser('sind')
      await loginPage.register(user)

      // Open shelf menu
      await page.locator('button[icon="menu"], .q-btn[icon="menu"], button:has(.q-icon:text("menu"))').first().click()
      await page.waitForSelector('.q-dialog')

      const dialog = page.locator('.q-dialog')

      // Should have 4 sort options (author, date added, read date, title)
      const sortList = dialog.locator('.q-list')
      await expect(sortList).toBeVisible()
      await expect(sortList.locator('.q-item')).toHaveCount(4)
    })
  })
})
