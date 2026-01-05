import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { HomePage } from './pages/home.page'
import { BookDialogPage } from './pages/book-dialog.page'
import { createTestUser, testBooks, createTestReview } from './fixtures/test-data'

test.describe('Reviews', () => {
  let loginPage: LoginPage
  let homePage: HomePage
  let bookDialog: BookDialogPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    homePage = new HomePage(page)
    bookDialog = new BookDialogPage(page)

    // Register and login a test user with unique data
    await loginPage.goto()
    const user = createTestUser('rev')
    await loginPage.register(user)

    // Add a book to library first
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.clickOnBookResult(0)
    await bookDialog.addToLibrary()
  })

  test('user sees review form when book is in library', async ({ page }) => {
    // Verify the review form is visible for books in the library
    await expect(page.locator('[data-testid="review-rating"]')).toBeVisible()
    await expect(page.locator('[data-testid="review-title-input"]')).toBeVisible()
    await expect(page.locator('[data-testid="review-content-input"]')).toBeVisible()
    await expect(page.locator('[data-testid="submit-review-btn"]')).toBeVisible()
  })

  test('rating component is interactive', async ({ page }) => {
    // Verify the rating component is visible and has 5 stars
    const ratingComponent = page.locator('[data-testid="review-rating"]')
    await expect(ratingComponent).toBeVisible()

    // There should be 5 rating options
    const stars = page.getByRole('radio', { name: /star \d/ })
    await expect(stars).toHaveCount(5)
  })

  test('user can fill review form fields', async ({ page }) => {
    const review = createTestReview()

    // Fill the title field
    const titleInput = page.locator('[data-testid="review-title-input"]')
    await titleInput.fill(review.title)
    await expect(titleInput).toHaveValue(review.title)

    // Fill the content field
    const contentInput = page.locator('[data-testid="review-content-input"]')
    await contentInput.fill(review.content)
    await expect(contentInput).toHaveValue(review.content)
  })

  test('submit button is visible and clickable', async ({ page }) => {
    // The submit button should be visible
    const submitBtn = page.locator('[data-testid="submit-review-btn"]')
    await expect(submitBtn).toBeVisible()
    await expect(submitBtn).toBeEnabled()
  })

  test('existing reviews section is visible', async ({ page }) => {
    // The existing reviews section should be visible
    await expect(page.getByText('Existing reviews')).toBeVisible()
  })
})
