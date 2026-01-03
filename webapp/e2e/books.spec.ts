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

  test('user can search for books', async () => {
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.expectSearchResults()
  })

  test('user can add book to library', async () => {
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.expectSearchResults()
    await homePage.clickOnBookResult(0)

    await bookDialog.addToLibrary()
    await bookDialog.expectInLibrary()
  })

  test('user can remove book from library', async () => {
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

  test('user can set read date', async () => {
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

  test('read date persists after closing and reopening dialog', async ({ page }) => {
    // Add a book to the library
    await homePage.searchBooks(testBooks.searchQuery)
    await homePage.clickOnBookResult(0)
    await bookDialog.addToLibrary()

    // Set a specific read date
    const testDate = '2024-06-15'
    await bookDialog.setReadDate(testDate)

    // Wait for save to complete
    await page.waitForTimeout(2000)

    // Verify the date is set before closing
    const dateBeforeClose = await bookDialog.getReadDate()
    expect(dateBeforeClose).toBe(testDate)

    // Close the dialog
    await bookDialog.close()

    // Wait for dialog to fully close
    await page.waitForSelector('[data-testid="close-dialog-btn"]', { state: 'hidden', timeout: 5000 })

    // Reopen the book from the library
    await homePage.clickOnLibraryBook(0)

    // Wait for data to load
    await page.waitForTimeout(1000)

    // Verify the date is still there
    await bookDialog.expectReadDate(testDate)
  })
})
