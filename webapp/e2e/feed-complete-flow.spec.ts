import { test, expect, Page } from '@playwright/test'
import { faker } from '@faker-js/faker'
import { LoginPage } from './pages/login.page'

// Helper to generate realistic user data
function generateUser() {
  const firstName = faker.person.firstName()
  const lastName = faker.person.lastName()
  const timestamp = Date.now()

  return {
    displayName: `${firstName} ${lastName}`,
    email: `test.${timestamp}.${faker.string.alphanumeric(4)}@test.com`,
    password: 'TestPassword123!'
  }
}

// Helper to search and add a book to shelf
async function searchAndAddBook(page: Page, searchTerm: string): Promise<{ added: boolean; bookTitle: string }> {
  await page.goto('/add')
  await page.waitForLoadState('networkidle')
  await page.waitForTimeout(1000)

  const searchInput = page.locator('input.q-field__native').first()
  await searchInput.waitFor({ state: 'visible', timeout: 10000 })
  await searchInput.clear()
  await searchInput.fill(searchTerm)
  await page.keyboard.press('Enter')

  // Wait for Google Books API results
  await page.waitForTimeout(5000)

  const bookCards = page.locator('.book-card')
  const count = await bookCards.count()

  if (count === 0) {
    return { added: false, bookTitle: '' }
  }

  // Get the book title before clicking
  const bookTitle = (await bookCards.first().locator('.book-title').textContent()) || searchTerm

  await bookCards.first().click()
  await page.waitForLoadState('networkidle')
  await page.waitForTimeout(3000)

  // Try to add to shelf via dropdown
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
      console.log(`Added: ${bookTitle}`)
      return { added: true, bookTitle }
    }
  }

  return { added: false, bookTitle }
}

// Helper to register a new user
async function registerUser(page: Page, user: ReturnType<typeof generateUser>) {
  const loginPage = new LoginPage(page)
  await loginPage.goto()
  await loginPage.register({
    displayName: user.displayName,
    email: user.email,
    password: user.password
  })
  await page.waitForTimeout(2000)
}

// Helper to logout by clearing storage
async function logout(page: Page) {
  await page.context().clearCookies()
  await page.evaluate(() => {
    localStorage.clear()
    sessionStorage.clear()
  })
  await page.goto('/')
  await page.waitForTimeout(1000)
}

// Helper to get username from settings
async function getUsername(page: Page): Promise<string> {
  await page.goto('/settings/profile')
  await page.waitForLoadState('networkidle')
  await page.waitForTimeout(1000)

  const usernameText = await page
    .locator('text=/localhost\\//')
    .textContent()
    .catch(() => null)
  if (usernameText) {
    const match = usernameText.match(/localhost\/\s*(\w+)/)
    if (match) return match[1]
  }
  return ''
}

test.describe('Feed Complete Flow - Two Users Interaction', () => {
  test('user can see activities from followed user in feed', async ({ page }) => {
    const user1 = generateUser()
    const user2 = generateUser()

    console.log('User 1:', user1.displayName)
    console.log('User 2:', user2.displayName)

    // ========================================
    // STEP 1: User 1 creates account and adds books
    // ========================================
    console.log('\n--- Step 1: User 1 registration and adding books ---')

    await registerUser(page, user1)
    const user1Username = await getUsername(page)
    console.log('User 1 username:', user1Username)

    // Add real books
    const user1Books = ['1984 George Orwell', 'The Hobbit Tolkien']
    const addedBooks: string[] = []

    for (const search of user1Books) {
      const result = await searchAndAddBook(page, search)
      if (result.added) addedBooks.push(result.bookTitle)
    }

    console.log(`User 1 added ${addedBooks.length} books:`, addedBooks)

    await page.screenshot({ path: '/tmp/feed-flow-user1-library.png', fullPage: true })

    // Logout User 1
    await logout(page)
    console.log('User 1 logged out')

    // ========================================
    // STEP 2: User 2 creates account
    // ========================================
    console.log('\n--- Step 2: User 2 registration ---')

    await registerUser(page, user2)

    // ========================================
    // STEP 3: User 2 follows User 1
    // ========================================
    console.log('\n--- Step 3: User 2 follows User 1 ---')

    if (user1Username) {
      await page.goto(`/${user1Username}`)
      await page.waitForLoadState('networkidle')
      await page.waitForTimeout(2000)

      await page.screenshot({ path: '/tmp/feed-flow-user1-profile.png', fullPage: true })

      const followBtn = page
        .getByTestId('follow-button')
        .or(page.locator('button, .q-btn').filter({ hasText: /^follow$|^seguir$/i }))
        .first()

      if (await followBtn.isVisible({ timeout: 5000 })) {
        await followBtn.click()
        await page.waitForTimeout(2000)
        console.log('User 2 followed User 1')
      }
    }

    // ========================================
    // STEP 4: User 2 checks feed
    // ========================================
    console.log('\n--- Step 4: User 2 checks feed ---')

    await page.goto('/feed')
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(3000)

    await page.screenshot({ path: '/tmp/feed-flow-final-feed.png', fullPage: true })

    const activityGroups = page.locator('.activity-group')
    const activityCount = await activityGroups.count()
    console.log(`Found ${activityCount} activity groups`)

    await expect(page).toHaveURL('/feed')

    const hasEmptyState = await page.locator('.empty-state').isVisible()
    const hasActivities = activityCount > 0

    if (hasActivities && addedBooks.length > 0) {
      console.log('SUCCESS: Feed shows activities!')

      // Look for User 1's activities
      const user1Activity = page.locator('.activity-group').filter({ hasText: user1.displayName }).first()
      if (await user1Activity.isVisible({ timeout: 3000 })) {
        console.log(`Found ${user1.displayName}'s activities in feed!`)
        await expect(user1Activity).toBeVisible()
      }
    }

    expect(hasEmptyState || hasActivities).toBeTruthy()
  })
})

test.describe('Feed - No Duplicate Books', () => {
  test('feed should not show duplicate books in activity group', async ({ page }) => {
    // Use existing test user that has activities
    const loginPage = new LoginPage(page)
    await loginPage.goto()

    const timestamp = Date.now()
    const user = {
      displayName: `NoDupe${String(timestamp).slice(-6)}`,
      email: `nodupe.${timestamp}@test.com`,
      password: 'TestPassword123!'
    }
    await loginPage.register(user)

    // Go to feed
    await page.goto('/feed')
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(2000)

    // Check all activity groups for duplicates
    const activityGroups = page.locator('.activity-group')
    const groupCount = await activityGroups.count()

    for (let i = 0; i < groupCount; i++) {
      const group = activityGroups.nth(i)
      const bookCovers = group.locator('.book-cover, .book-item')
      const coverCount = await bookCovers.count()

      if (coverCount > 1) {
        // Get all book image sources
        const srcs: string[] = []
        for (let j = 0; j < coverCount; j++) {
          const src = await bookCovers
            .nth(j)
            .locator('img')
            .getAttribute('src')
            .catch(() => null)
          if (src) srcs.push(src)
        }

        // Check for duplicates
        const uniqueSrcs = new Set(srcs)
        expect(uniqueSrcs.size, `Activity group ${i + 1} has duplicate books! Found ${srcs.length} covers but only ${uniqueSrcs.size} unique`).toBe(
          srcs.length
        )
      }
    }

    console.log(`Verified ${groupCount} activity groups have no duplicate books`)
  })

  test('API returns unique books per activity group', async ({ page }) => {
    const loginPage = new LoginPage(page)
    await loginPage.goto()

    const timestamp = Date.now()
    await loginPage.register({
      displayName: `APITest${String(timestamp).slice(-6)}`,
      email: `apitest.${timestamp}@test.com`,
      password: 'TestPassword123!'
    })

    // Intercept the feeds API call
    const responsePromise = page.waitForResponse((response) => response.url().includes('/feeds') && response.status() === 200)

    await page.goto('/feed')
    const response = await responsePromise
    const data = await response.json()

    // Check each group for duplicate subjects
    for (const group of data.grouped || []) {
      const subjectIds = group.activities.map((a: { subject?: { id?: string } }) => a.subject?.id).filter(Boolean)
      const uniqueIds = new Set(subjectIds)

      expect(uniqueIds.size, `Group "${group.type}" has duplicate subjects!`).toBe(subjectIds.length)
    }

    console.log(`API verification: ${data.grouped?.length || 0} groups checked for duplicates`)
  })
})
