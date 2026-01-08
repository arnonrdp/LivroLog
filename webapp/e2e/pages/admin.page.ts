import { Page, expect } from '@playwright/test'

export class AdminPage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/admin')
  }

  async gotoUsers() {
    await this.page.goto('/admin/users')
  }

  async gotoBooks() {
    await this.page.goto('/admin/books')
  }

  async expectToBeOnAdminPage() {
    await expect(this.page).toHaveURL(/\/admin/)
  }

  async expectToBeRedirectedToHome() {
    await expect(this.page).toHaveURL(/\/home/)
  }

  async expectUsersTableVisible() {
    await expect(this.page.locator('.q-table')).toBeVisible()
    await expect(this.page.locator('text=Email')).toBeVisible()
  }

  async expectBooksTableVisible() {
    await expect(this.page.locator('.q-table')).toBeVisible()
    await expect(this.page.locator('text=ISBN')).toBeVisible()
  }

  async searchUsers(query: string) {
    await this.page.locator('input[placeholder*="usuários"], input[placeholder*="users"]').fill(query)
    await this.page.waitForTimeout(500) // debounce
  }

  async searchBooks(query: string) {
    await this.page.locator('input[placeholder*="livros"], input[placeholder*="books"]').fill(query)
    await this.page.waitForTimeout(500) // debounce
  }

  async clickEditFirstBook() {
    await this.page.locator('.q-table tbody tr').first().locator('button[aria-label="Edit"], button:has(i.q-icon:text("edit"))').first().click()
  }

  async clickDeleteFirstBook() {
    await this.page.locator('.q-table tbody tr').first().locator('button:has(.text-negative), button.text-negative').first().click()
  }

  async clickAddBookButton() {
    await this.page.locator('button:has-text("Adicionar livro"), button:has-text("Add book")').click()
  }

  async expectAddDialogVisible() {
    await expect(this.page.locator('.q-dialog')).toBeVisible()
    await expect(this.page.locator('.q-dialog .text-h6:has-text("Adicionar livro"), .q-dialog .text-h6:has-text("Add book")')).toBeVisible()
  }

  async expectEditDialogVisible() {
    await expect(this.page.locator('.q-dialog')).toBeVisible()
    await expect(this.page.locator('.q-dialog .text-h6')).toBeVisible()
  }

  async expectDeleteDialogVisible() {
    await expect(this.page.locator('.q-dialog')).toBeVisible()
    await expect(this.page.locator('.q-dialog .q-icon[style*="warning"], .q-dialog i:text("warning")')).toBeVisible()
  }

  async fillBookForm(data: {
    title?: string
    authors?: string
    isbn?: string
    amazonAsin?: string
    googleId?: string
    language?: string
    publisher?: string
    pageCount?: string
    description?: string
  }) {
    const inputs = this.page.locator('.q-dialog input')
    const textarea = this.page.locator('.q-dialog textarea')

    if (data.title) {
      await inputs.nth(0).fill(data.title) // Title
    }
    if (data.authors) {
      await inputs.nth(1).fill(data.authors) // Authors
    }
    if (data.isbn) {
      await inputs.nth(2).fill(data.isbn) // ISBN
    }
    if (data.amazonAsin) {
      await inputs.nth(3).fill(data.amazonAsin) // Amazon ASIN
    }
    if (data.googleId) {
      await inputs.nth(4).fill(data.googleId) // Google ID
    }
    if (data.language) {
      await inputs.nth(5).fill(data.language) // Language
    }
    if (data.publisher) {
      await inputs.nth(6).fill(data.publisher) // Publisher
    }
    if (data.pageCount) {
      await inputs.nth(7).fill(data.pageCount) // Page count
    }
    if (data.description) {
      await textarea.fill(data.description) // Description
    }
  }

  async fillEditForm(data: { title?: string; authors?: string; isbn?: string }) {
    await this.fillBookForm(data)
  }

  async saveEdit() {
    await this.page.locator('.q-dialog button:has-text("Salvar"), .q-dialog button:has-text("Save")').click()
  }

  async cancelDialog() {
    await this.page.locator('.q-dialog button:has-text("Cancelar"), .q-dialog button:has-text("Cancel")').click()
  }

  async confirmDelete() {
    await this.page.locator('.q-dialog button:has-text("Excluir"), .q-dialog button:has-text("Delete")').last().click()
  }

  async expectNotification(type: 'positive' | 'negative') {
    await expect(this.page.locator(`.q-notification.bg-${type}`)).toBeVisible({ timeout: 5000 })
  }

  async getUsersCount() {
    const text = await this.page.locator('.q-table__bottom').textContent()
    const match = text?.match(/(\d+)/)
    return match ? parseInt(match[1]) : 0
  }

  async getBooksCount() {
    const text = await this.page.locator('.q-table__bottom').textContent()
    const match = text?.match(/(\d+)/)
    return match ? parseInt(match[1]) : 0
  }

  async clickUsersTab() {
    await this.page.locator('.q-tabs .q-tab:has-text("Usuários"), .q-tabs .q-tab:has-text("Users")').click()
  }

  async clickBooksTab() {
    await this.page.locator('.q-tabs .q-tab:has-text("Livros"), .q-tabs .q-tab:has-text("Books")').click()
  }

  async getTableRowsCount() {
    return await this.page.locator('.q-table tbody tr').count()
  }
}
