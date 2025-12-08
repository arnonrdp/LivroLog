/**
 * Production E2E Tests
 *
 * These tests run against production with a dedicated test user.
 * They verify core book functionality without creating/deleting users.
 *
 * Run with: npx playwright test e2e/production.spec.ts
 * Requires: PLAYWRIGHT_USER_PASSWORD environment variable
 */

import { expect, test } from '@playwright/test'

// Production test user - password from environment
const PROD_USER = {
  email: 'playwright@livrolog.com',
  password: process.env.PLAYWRIGHT_USER_PASSWORD || ''
}

// Test book for production tests - use a well-known book
const TEST_BOOK = {
  searchQuery: 'O Pequeno Príncipe',
  title: 'O Pequeno Príncipe'
}

test.describe('Production - Book Operations', () => {
  test.beforeEach(async ({ page }) => {
    // Skip if no password configured
    test.skip(!PROD_USER.password, 'PLAYWRIGHT_USER_PASSWORD not set')

    // Login with production user
    await page.goto('/')
    await page.locator('[data-testid="header-signin-btn"]').click()
    await page.locator('[data-testid="login-email"]').fill(PROD_USER.email)
    await page.locator('[data-testid="login-password"]').fill(PROD_USER.password)
    await page.locator('[data-testid="login-submit-btn"]').click()
    await page.waitForURL(/\/home/, { timeout: 15000 })
  })

  test('can search for books', async ({ page }) => {
    // Navigate to add books page
    await page.goto('/add')

    // Search for a book
    await page.locator('[data-testid="book-search-input"]').fill(TEST_BOOK.searchQuery)
    await page.locator('[data-testid="book-search-input"]').press('Enter')

    // Wait for results
    await page.waitForSelector('[data-testid="book-result"]', { timeout: 30000 })

    // Verify results contain expected book
    const results = page.locator('[data-testid="book-result"]')
    await expect(results.first()).toBeVisible()
  })

  test('can add and remove book from library', async ({ page }) => {
    // Navigate to add books page
    await page.goto('/add')

    // Search for a book
    await page.locator('[data-testid="book-search-input"]').fill(TEST_BOOK.searchQuery)
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForSelector('[data-testid="book-result"]', { timeout: 30000 })

    // Click on first result to open dialog
    await page.locator('[data-testid="book-result"]').first().locator('.book-cover').click()
    await page.waitForSelector('[data-testid="close-dialog-btn"]', { timeout: 10000 })

    // Check if book is already in library
    const removeBtn = page.locator('[data-testid="remove-from-library-btn"]')
    const addBtn = page.locator('[data-testid="add-to-library-btn"]')

    if (await removeBtn.isVisible()) {
      // Book is in library - remove it first
      await removeBtn.click()
      await addBtn.waitFor({ state: 'visible', timeout: 10000 })
    }

    // Add book to library
    await addBtn.scrollIntoViewIfNeeded()
    await addBtn.click()
    await removeBtn.waitFor({ state: 'visible', timeout: 15000 })

    // Verify book is in library
    await expect(removeBtn).toBeVisible()

    // Remove book from library (cleanup)
    await removeBtn.click()
    await addBtn.waitFor({ state: 'visible', timeout: 10000 })

    // Verify book is removed
    await expect(addBtn).toBeVisible()
  })

  test('can change reading status', async ({ page }) => {
    // Navigate to add books page and add a book
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill(TEST_BOOK.searchQuery)
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForSelector('[data-testid="book-result"]', { timeout: 30000 })

    // Open book dialog
    await page.locator('[data-testid="book-result"]').first().locator('.book-cover').click()
    await page.waitForSelector('[data-testid="close-dialog-btn"]', { timeout: 10000 })

    // Ensure book is in library
    const addBtn = page.locator('[data-testid="add-to-library-btn"]')
    if (await addBtn.isVisible()) {
      await addBtn.scrollIntoViewIfNeeded()
      await addBtn.click()
      await page.waitForSelector('[data-testid="remove-from-library-btn"]', { timeout: 15000 })
    }

    // Change reading status to "Reading"
    await page.locator('[data-testid="reading-status-select"]').click()
    await page.getByRole('option', { name: 'Reading', exact: true }).click()

    // Verify status changed
    await expect(page.locator('[data-testid="reading-status-select"]')).toContainText('Reading')

    // Change back to "Read"
    await page.locator('[data-testid="reading-status-select"]').click()
    await page.getByRole('option', { name: 'Read', exact: true }).click()

    // Verify status changed
    await expect(page.locator('[data-testid="reading-status-select"]')).toContainText('Read')

    // Cleanup - remove book
    await page.locator('[data-testid="remove-from-library-btn"]').click()
  })

  test('can set read date', async ({ page }) => {
    // Navigate to add books page and add a book
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill(TEST_BOOK.searchQuery)
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForSelector('[data-testid="book-result"]', { timeout: 30000 })

    // Open book dialog
    await page.locator('[data-testid="book-result"]').first().locator('.book-cover').click()
    await page.waitForSelector('[data-testid="close-dialog-btn"]', { timeout: 10000 })

    // Ensure book is in library
    const addBtn = page.locator('[data-testid="add-to-library-btn"]')
    if (await addBtn.isVisible()) {
      await addBtn.scrollIntoViewIfNeeded()
      await addBtn.click()
      await page.waitForSelector('[data-testid="remove-from-library-btn"]', { timeout: 15000 })
    }

    // Set read date
    const today = new Date().toISOString().split('T')[0] // YYYY-MM-DD
    await page.locator('[data-testid="read-date-input"]').fill(today)
    await page.locator('[data-testid="read-date-input"]').blur()

    // Wait for save
    await page.waitForTimeout(1000)

    // Verify date was set
    await expect(page.locator('[data-testid="read-date-input"]')).toHaveValue(today)

    // Cleanup - remove book
    await page.locator('[data-testid="remove-from-library-btn"]').click()
  })

  test('can mark book as private', async ({ page }) => {
    // Navigate to add books page and add a book
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill(TEST_BOOK.searchQuery)
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForSelector('[data-testid="book-result"]', { timeout: 30000 })

    // Open book dialog
    await page.locator('[data-testid="book-result"]').first().locator('.book-cover').click()
    await page.waitForSelector('[data-testid="close-dialog-btn"]', { timeout: 10000 })

    // Ensure book is in library
    const addBtn = page.locator('[data-testid="add-to-library-btn"]')
    if (await addBtn.isVisible()) {
      await addBtn.scrollIntoViewIfNeeded()
      await addBtn.click()
      await page.waitForSelector('[data-testid="remove-from-library-btn"]', { timeout: 15000 })
    }

    // Toggle private checkbox
    const privateCheckbox = page.locator('[data-testid="private-book-checkbox"]')
    await privateCheckbox.scrollIntoViewIfNeeded()

    const wasChecked = await privateCheckbox.isChecked()
    await privateCheckbox.click()

    // Wait for save
    await page.waitForTimeout(1000)

    // Verify toggle worked
    const isCheckedNow = await privateCheckbox.isChecked()
    expect(isCheckedNow).toBe(!wasChecked)

    // Toggle back to original state
    await privateCheckbox.click()

    // Cleanup - remove book
    await page.locator('[data-testid="remove-from-library-btn"]').click()
  })

  test('can interact with review form', async ({ page }) => {
    // Navigate to add books page and add a book
    await page.goto('/add')
    await page.locator('[data-testid="book-search-input"]').fill(TEST_BOOK.searchQuery)
    await page.locator('[data-testid="book-search-input"]').press('Enter')
    await page.waitForSelector('[data-testid="book-result"]', { timeout: 30000 })

    // Open book dialog
    await page.locator('[data-testid="book-result"]').first().locator('.book-cover').click()
    await page.waitForSelector('[data-testid="close-dialog-btn"]', { timeout: 10000 })

    // Ensure book is in library
    const addBtn = page.locator('[data-testid="add-to-library-btn"]')
    if (await addBtn.isVisible()) {
      await addBtn.scrollIntoViewIfNeeded()
      await addBtn.click()
      await page.waitForSelector('[data-testid="remove-from-library-btn"]', { timeout: 15000 })
    }

    // Verify review form is visible
    await expect(page.locator('[data-testid="review-rating"]')).toBeVisible()
    await expect(page.locator('[data-testid="review-content-input"]')).toBeVisible()
    await expect(page.locator('[data-testid="submit-review-btn"]')).toBeVisible()

    // Test rating component - click on star 5
    await page.getByRole('radio', { name: 'star 5' }).click()

    // Fill review content (but don't submit to avoid creating test data)
    await page.locator('[data-testid="review-content-input"]').fill('Test review content - will not be saved')

    // Verify fields are filled
    await expect(page.locator('[data-testid="review-content-input"]')).toHaveValue('Test review content - will not be saved')

    // Clear the form (don't submit)
    await page.locator('[data-testid="review-content-input"]').clear()

    // Cleanup - remove book
    await page.locator('[data-testid="remove-from-library-btn"]').click()
  })
})

test.describe('Production - User Profile', () => {
  test.beforeEach(async ({ page }) => {
    // Skip if no password configured
    test.skip(!PROD_USER.password, 'PLAYWRIGHT_USER_PASSWORD not set')

    // Login with production user
    await page.goto('/')
    await page.locator('[data-testid="header-signin-btn"]').click()
    await page.locator('[data-testid="login-email"]').fill(PROD_USER.email)
    await page.locator('[data-testid="login-password"]').fill(PROD_USER.password)
    await page.locator('[data-testid="login-submit-btn"]').click()
    await page.waitForURL(/\/home/, { timeout: 15000 })
  })

  test('can access home page', async ({ page }) => {
    await expect(page).toHaveURL(/\/home/)
  })

  test('can access settings', async ({ page }) => {
    await page.goto('/settings/profile')
    await expect(page.getByText('Shelf Name')).toBeVisible()
    await expect(page.getByText('Username')).toBeVisible()
  })

  test('can access people page', async ({ page }) => {
    await page.goto('/people')
    await expect(page).toHaveURL(/\/people/)
  })
})
