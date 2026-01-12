import { test, expect } from '@playwright/test'
import { AdminPage } from './pages/admin.page'
import { LoginPage } from './pages/login.page'
import { createTestUser } from './fixtures/test-data'

test.describe('Admin Panel', () => {
  let adminPage: AdminPage
  let loginPage: LoginPage

  test.beforeEach(async ({ page }) => {
    adminPage = new AdminPage(page)
    loginPage = new LoginPage(page)
  })

  test.describe('Access Control', () => {
    test('non-admin user cannot access admin panel', async ({ page }) => {
      // Register a regular user
      const user = createTestUser('noadm')
      await loginPage.goto()
      await loginPage.register(user)

      // Try to access admin panel
      await page.goto('/admin')

      // Should be redirected to home
      await expect(page).toHaveURL(/\/home/)
    })

    test('non-authenticated user cannot access admin panel', async ({ page }) => {
      // Try to access admin panel without logging in
      await page.goto('/admin')

      // Should stay on landing page or be redirected
      await expect(page).not.toHaveURL(/\/admin/)
    })
  })

  test.describe('Admin Users Management', () => {
    test.beforeEach(async ({ page }) => {
      // Create admin user via API and login
      const adminUser = createTestUser('adm')

      // Register user first
      await loginPage.goto()
      await loginPage.register(adminUser)

      // Make user admin via API (requires direct DB access or API endpoint)
      // For now, we'll use the existing arnonrodrigues admin user
      await page.goto('/settings/account')
      await page.locator('[data-testid="logout-button"]').click()

      // Login as existing admin user
      await loginPage.goto()
      await loginPage.login('arnonrodrigues@gmail.com', 'TestPassword123!')
    })

    test.skip('admin can view users table', async () => {
      await adminPage.gotoUsers()
      await adminPage.expectUsersTableVisible()
    })

    test.skip('admin can search users', async () => {
      await adminPage.gotoUsers()
      await adminPage.searchUsers('arnon')

      // Should filter results
      const rows = await adminPage.getTableRowsCount()
      expect(rows).toBeGreaterThan(0)
    })

    test.skip('users table shows correct columns', async ({ page }) => {
      await adminPage.gotoUsers()

      await expect(page.locator('th:has-text("Nome"), th:has-text("Name")')).toBeVisible()
      await expect(page.locator('th:has-text("Usuário"), th:has-text("Username")')).toBeVisible()
      await expect(page.locator('th:has-text("Email")')).toBeVisible()
      await expect(page.locator('th:has-text("Livros"), th:has-text("Books")')).toBeVisible()
    })
  })

  test.describe('Admin Books Management', () => {
    test.skip('admin can view books table', async () => {
      await adminPage.gotoBooks()
      await adminPage.expectBooksTableVisible()
    })

    test.skip('admin can search books', async () => {
      await adminPage.gotoBooks()
      await adminPage.searchBooks('Dom Casmurro')

      const rows = await adminPage.getTableRowsCount()
      expect(rows).toBeGreaterThan(0)
    })

    test.skip('books table shows correct columns', async ({ page }) => {
      await adminPage.gotoBooks()

      await expect(page.locator('th:has-text("Título"), th:has-text("Title")')).toBeVisible()
      await expect(page.locator('th:has-text("Autores"), th:has-text("Authors")')).toBeVisible()
      await expect(page.locator('th:has-text("ISBN")')).toBeVisible()
      await expect(page.locator('th:has-text("Idioma"), th:has-text("Language")')).toBeVisible()
    })

    test.skip('admin can open edit dialog for a book', async () => {
      await adminPage.gotoBooks()
      await adminPage.clickEditFirstBook()
      await adminPage.expectEditDialogVisible()
    })

    test.skip('admin can cancel edit dialog', async () => {
      await adminPage.gotoBooks()
      await adminPage.clickEditFirstBook()
      await adminPage.expectEditDialogVisible()
      await adminPage.cancelDialog()

      // Dialog should be closed
      await expect(adminPage['page'].locator('.q-dialog')).not.toBeVisible()
    })

    test.skip('admin can open delete confirmation dialog', async () => {
      await adminPage.gotoBooks()
      await adminPage.clickDeleteFirstBook()
      await adminPage.expectDeleteDialogVisible()
    })

    test.skip('admin can cancel delete dialog', async () => {
      await adminPage.gotoBooks()
      await adminPage.clickDeleteFirstBook()
      await adminPage.expectDeleteDialogVisible()
      await adminPage.cancelDialog()

      // Dialog should be closed
      await expect(adminPage['page'].locator('.q-dialog')).not.toBeVisible()
    })

    test.skip('add book button is visible', async ({ page }) => {
      await adminPage.gotoBooks()
      await adminPage.expectBooksTableVisible()

      // Check that the add book button is visible
      await expect(page.locator('button:has-text("Adicionar livro"), button:has-text("Add book")')).toBeVisible()
    })

    test.skip('admin can open add book dialog', async () => {
      await adminPage.gotoBooks()
      await adminPage.clickAddBookButton()
      await adminPage.expectAddDialogVisible()
    })

    test.skip('admin can cancel add book dialog', async ({ page }) => {
      await adminPage.gotoBooks()
      await adminPage.clickAddBookButton()
      await adminPage.expectAddDialogVisible()
      await adminPage.cancelDialog()

      // Dialog should be closed
      await expect(page.locator('.q-dialog')).not.toBeVisible()
    })

    test.skip('add book dialog has all required fields', async ({ page }) => {
      await adminPage.gotoBooks()
      await adminPage.clickAddBookButton()
      await adminPage.expectAddDialogVisible()

      // Check that all input fields are present
      const inputs = page.locator('.q-dialog input')
      const textarea = page.locator('.q-dialog textarea')

      // Should have multiple input fields
      await expect(inputs).toHaveCount(10) // title, authors, isbn, asin, google_id, language, publisher, page_count, published_date, thumbnail

      // Should have description textarea
      await expect(textarea).toBeVisible()
    })

    test.skip('admin can create a new book', async ({ page }) => {
      await adminPage.gotoBooks()

      const _initialRowCount = await adminPage.getTableRowsCount()

      await adminPage.clickAddBookButton()
      await adminPage.expectAddDialogVisible()

      const uniqueTitle = `E2E Test Book ${Date.now()}`
      await adminPage.fillBookForm({
        title: uniqueTitle,
        authors: 'E2E Test Author',
        isbn: '9781234567890',
        language: 'en',
        publisher: 'E2E Publisher',
        pageCount: '250',
        description: 'A book created during E2E testing.'
      })

      await adminPage.saveEdit()

      // Wait for notification
      await adminPage.expectNotification('positive')

      // Dialog should be closed
      await expect(page.locator('.q-dialog')).not.toBeVisible()

      // Search for the created book
      await adminPage.searchBooks(uniqueTitle)
      await page.waitForTimeout(500)

      // The book should appear in the table
      await expect(page.locator(`.q-table tbody tr:has-text("${uniqueTitle}")`)).toBeVisible()
    })

    test.skip('add book requires title', async ({ page }) => {
      await adminPage.gotoBooks()
      await adminPage.clickAddBookButton()
      await adminPage.expectAddDialogVisible()

      // Try to save without filling the title
      await adminPage.fillBookForm({
        authors: 'Some Author'
      })

      await adminPage.saveEdit()

      // Should show error notification or validation message
      await adminPage.expectNotification('negative')

      // Dialog should still be open
      await expect(page.locator('.q-dialog')).toBeVisible()
    })

    test.skip('admin can create book with amazon asin', async ({ page }) => {
      await adminPage.gotoBooks()
      await adminPage.clickAddBookButton()
      await adminPage.expectAddDialogVisible()

      const uniqueTitle = `ASIN Test Book ${Date.now()}`
      await adminPage.fillBookForm({
        title: uniqueTitle,
        authors: 'ASIN Author',
        amazonAsin: 'B08E2ETESTCODE'
      })

      await adminPage.saveEdit()

      // Wait for notification
      await adminPage.expectNotification('positive')

      // Search for the created book
      await adminPage.searchBooks('B08E2ETESTCODE')
      await page.waitForTimeout(500)

      // The book should appear in the table
      await expect(page.locator(`.q-table tbody tr:has-text("${uniqueTitle}")`)).toBeVisible()
    })
  })

  test.describe('Tab Navigation', () => {
    test.skip('admin can switch between users and books tabs', async ({ page }) => {
      await adminPage.gotoUsers()
      await adminPage.expectUsersTableVisible()

      await adminPage.clickBooksTab()
      await expect(page).toHaveURL(/\/admin\/books/)
      await adminPage.expectBooksTableVisible()

      await adminPage.clickUsersTab()
      await expect(page).toHaveURL(/\/admin\/users/)
      await adminPage.expectUsersTableVisible()
    })

    test.skip('admin icon is visible in header for admin users', async ({ page }) => {
      await adminPage.goto()
      await expect(page.locator('.q-tabs .q-tab:has(.q-icon:text("admin_panel_settings"))')).toBeVisible()
    })
  })
})
