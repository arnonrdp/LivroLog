import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { HomePage } from './pages/home.page'
import { BookDialogPage } from './pages/book-dialog.page'
import { createTestUser, testBooks } from './fixtures/test-data'

test.describe('Books', () => {
  let loginPage: LoginPage
  let homePage: HomePage
  let bookDialog: BookDialogPage

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page)
    homePage = new HomePage(page)
    bookDialog = new BookDialogPage(page)

    // Register and login a test user with unique data
    await loginPage.goto()
    const user = createTestUser('book')
    await loginPage.register(user)
  })

  test('user can search for books', async ({ page }) => {
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.expectSearchResults()
  })

  test('user can add book to library', async ({ page }) => {
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.expectSearchResults()
    await homePage.clickOnBookResult(0)

    await bookDialog.addToLibrary()
    await bookDialog.expectInLibrary()
  })

  test('user can remove book from library', async ({ page }) => {
    // First add a book
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.clickOnBookResult(0)
    await bookDialog.addToLibrary()
    await bookDialog.expectInLibrary()

    // Remove the book
    await bookDialog.removeFromLibrary()
    await bookDialog.expectNotInLibrary()
  })

  test('user can change reading status', async ({ page }) => {
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.clickOnBookResult(0)
    await bookDialog.addToLibrary()

    await bookDialog.setReadingStatus('reading')
    // Verify the status was changed (case-insensitive)
    await expect(page.locator('[data-testid="reading-status-select"]')).toContainText('Reading')
  })

  test('user can set read date', async ({ page }) => {
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.clickOnBookResult(0)
    await bookDialog.addToLibrary()
    await bookDialog.setReadingStatus('read')

    const today = new Date().toISOString().split('T')[0]
    await bookDialog.setReadDate(today)
  })

  test('user can mark book as private', async ({ page }) => {
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.clickOnBookResult(0)
    await bookDialog.addToLibrary()

    await bookDialog.setPrivate(true)
    // Verify the checkbox is checked
    await expect(page.locator('[data-testid="private-book-checkbox"]')).toBeChecked()
  })
})
