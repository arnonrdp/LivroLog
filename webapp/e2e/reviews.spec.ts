import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { HomePage } from './pages/home.page'
import { BookDialogPage } from './pages/book-dialog.page'
import { testUsers, testBooks, testReview } from './fixtures/test-data'

test.describe('Reviews', () => {
  let loginPage: LoginPage
  let homePage: HomePage
  let bookDialog: BookDialogPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    homePage = new HomePage(page)
    bookDialog = new BookDialogPage(page)

    // Register and login a test user
    await loginPage.goto()
    const user = {
      ...testUsers.primary,
      email: `reviews.test.${Date.now()}@test.com`,
      username: `reviews_test_${Date.now()}`,
    }
    await loginPage.register(user)

    // Add a book to library first
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.clickOnBookResult(0)
    await bookDialog.addToLibrary()
  })

  test('user can create a review', async ({ page }) => {
    await bookDialog.writeReview(testReview.title, testReview.content, testReview.rating)
    await bookDialog.expectReviewVisible(testReview.title)
  })

  test('user can edit their review', async ({ page }) => {
    await bookDialog.writeReview(testReview.title, testReview.content, testReview.rating)

    const updatedTitle = 'Updated Review Title'
    const updatedContent = 'This is the updated review content.'
    await bookDialog.editReview(updatedTitle, updatedContent)

    await bookDialog.expectReviewVisible(updatedTitle)
  })

  test('user can delete their review', async ({ page }) => {
    await bookDialog.writeReview(testReview.title, testReview.content, testReview.rating)
    await bookDialog.expectReviewVisible(testReview.title)

    await bookDialog.deleteReview()

    // Review should no longer be visible
    await expect(page.locator('[data-testid="user-review"]')).not.toBeVisible()
  })

  test('user can change review visibility', async ({ page }) => {
    await bookDialog.writeReview(testReview.title, testReview.content, testReview.rating)

    // Toggle visibility
    await page.locator('[data-testid="review-visibility-toggle"]').click()

    // Verify visibility changed
    await expect(page.locator('[data-testid="review-visibility-status"]')).toContainText(/friends|private/)
  })
})
