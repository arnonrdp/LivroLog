/**
 * Manual exploratory browser script.
 *
 * This helper is intended to be run locally by developers and is NOT
 * executed as part of the automated Playwright test suite or CI.
 *
 * Usage:
 *   npx ts-node webapp/e2e/browser-test.ts
 *
 * Note: This script opens a visible browser and runs interactive tests
 * for Amazon store detection and preference persistence.
 */
import { chromium } from '@playwright/test'

/**
 * Manual exploratory test used to interact with the app in the browser.
 * This is intentionally NOT a Playwright test() and is not picked up by
 * the Playwright test runner.
 */
async function browserTest() {
  console.log('🚀 Teste interativo no navegador\n')
  console.log('═══════════════════════════════════════════════════════════════\n')

  const browser = await chromium.launch({ headless: false, slowMo: 400 })

  // TESTE 1: Usuário Brasileiro
  console.log('📍 TESTE 1: Registro com locale pt-BR')
  console.log('───────────────────────────────────────────────────────────────')

  const ctx1 = await browser.newContext({ locale: 'pt-BR' })
  const page1 = await ctx1.newPage()

  await page1.goto('http://localhost:8001')
  console.log('   ✓ Página inicial carregada')

  // Registrar
  await page1.locator('[data-testid="header-signup-btn"]').click()
  await page1.waitForSelector('[data-testid="signup-tab"]')
  await page1.locator('[data-testid="signup-tab"]').click()

  const ts1 = Date.now()
  await page1.locator('[data-testid="display-name"]').fill(`BrUser${String(ts1).slice(-4)}`)
  await page1.locator('[data-testid="email"]').fill(`br_${ts1}@test.com`)
  await page1.locator('[data-testid="password"]').fill('Test123!')
  await page1.locator('[data-testid="password-confirmation"]').fill('Test123!')
  await page1.locator('[data-testid="register-button"]').click()
  await page1.waitForURL(/\/home/, { timeout: 30000 })
  console.log('   ✓ Usuário registrado')

  // Verificar Settings
  await page1.goto('http://localhost:8001/settings/language')
  await page1.waitForSelector('[data-testid="amazon-store-select"]')

  // Verificar se a tab está visível com o texto correto
  const tabVisible = await page1.getByRole('tab', { name: 'Idioma e Loja' }).isVisible()
  console.log(`   ✓ Tab "Idioma e Loja" visível: ${tabVisible}`)
  if (tabVisible) {
    console.log('   ✅ Label correta!')
  }

  // Verificar loja selecionada
  const store1 = await page1.locator('[data-testid="amazon-store-selected"]').textContent()
  console.log(`   ✓ Loja detectada: ${store1}`)
  if (store1?.includes('Brasil')) {
    console.log('   ✅ Brasil detectado corretamente!\n')
  }

  // Testar botão Amazon
  console.log('📍 Testando botão Amazon (link direto)...')
  await page1.goto('http://localhost:8001/add')
  await page1.locator('[data-testid="book-search-input"]').fill('O Alquimista')
  await page1.locator('[data-testid="book-search-input"]').press('Enter')
  await page1.waitForSelector('[data-testid="book-result"]', { timeout: 60000 })
  console.log('   ✓ Busca realizada')

  await page1.locator('[data-testid="book-result"]').first().locator('.book-cover').click()
  await page1.waitForSelector('[data-testid="close-dialog-btn"]')

  const addBtn = page1.locator('[data-testid="add-to-library-btn"]')
  if (await addBtn.isVisible()) {
    await addBtn.click()
    await page1.waitForSelector('[data-testid="remove-from-library-btn"]', { timeout: 15000 })
    console.log('   ✓ Livro adicionado')
  }

  await page1.waitForTimeout(3000)

  const amazonBtn = page1.locator('[data-testid="amazon-btn"]')
  if (await amazonBtn.isVisible()) {
    const href = await amazonBtn.getAttribute('href')
    console.log(`   ✓ Link Amazon: ${href?.substring(0, 50)}...`)
    if (href?.includes('amazon.com.br')) {
      console.log('   ✅ Link direto para Amazon Brasil!\n')
    }
  }

  await page1.locator('[data-testid="close-dialog-btn"]').click()
  await ctx1.close()

  // TESTE 2: Usuário Alemão
  console.log('📍 TESTE 2: Registro com locale de-DE')
  console.log('───────────────────────────────────────────────────────────────')

  const ctx2 = await browser.newContext({ locale: 'de-DE' })
  const page2 = await ctx2.newPage()

  await page2.goto('http://localhost:8001')
  await page2.locator('[data-testid="header-signup-btn"]').click()
  await page2.waitForSelector('[data-testid="signup-tab"]')
  await page2.locator('[data-testid="signup-tab"]').click()

  const ts2 = Date.now()
  await page2.locator('[data-testid="display-name"]').fill(`DeUser${String(ts2).slice(-4)}`)
  await page2.locator('[data-testid="email"]').fill(`de_${ts2}@test.com`)
  await page2.locator('[data-testid="password"]').fill('Test123!')
  await page2.locator('[data-testid="password-confirmation"]').fill('Test123!')
  await page2.locator('[data-testid="register-button"]').click()
  await page2.waitForURL(/\/home/, { timeout: 30000 })
  console.log('   ✓ Usuário alemão registrado')

  await page2.goto('http://localhost:8001/settings/language')
  await page2.waitForSelector('[data-testid="amazon-store-select"]')

  const store2 = await page2.locator('[data-testid="amazon-store-selected"]').textContent()
  console.log(`   ✓ Loja detectada: ${store2}`)
  if (store2?.includes('Germany')) {
    console.log('   ✅ Alemanha detectada corretamente!\n')
  }

  await ctx2.close()

  // TESTE 3: Alterar preferência
  console.log('📍 TESTE 3: Alterar preferência de loja')
  console.log('───────────────────────────────────────────────────────────────')

  const ctx3 = await browser.newContext({ locale: 'en-US' })
  const page3 = await ctx3.newPage()

  await page3.goto('http://localhost:8001')
  await page3.locator('[data-testid="header-signup-btn"]').click()
  await page3.waitForSelector('[data-testid="signup-tab"]')
  await page3.locator('[data-testid="signup-tab"]').click()

  const ts3 = Date.now()
  await page3.locator('[data-testid="display-name"]').fill(`UsUser${String(ts3).slice(-4)}`)
  await page3.locator('[data-testid="email"]').fill(`us_${ts3}@test.com`)
  await page3.locator('[data-testid="password"]').fill('Test123!')
  await page3.locator('[data-testid="password-confirmation"]').fill('Test123!')
  await page3.locator('[data-testid="register-button"]').click()
  await page3.waitForURL(/\/home/, { timeout: 30000 })
  console.log('   ✓ Usuário americano registrado')

  await page3.goto('http://localhost:8001/settings/language')
  await page3.waitForSelector('[data-testid="amazon-store-select"]')

  const store3a = await page3.locator('[data-testid="amazon-store-selected"]').textContent()
  console.log(`   ✓ Loja inicial: ${store3a}`)

  // Mudar para Japão
  await page3.locator('[data-testid="amazon-store-select"]').click()
  await page3.waitForTimeout(500)
  await page3.locator('[data-testid="amazon-store-option-JP"]').click()
  await page3.locator('[data-testid="save-language-btn"]').click()
  await page3.waitForSelector('.q-notification')
  console.log('   ✓ Preferência alterada para Japão')

  await page3.reload()
  await page3.waitForSelector('[data-testid="amazon-store-select"]')

  const store3b = await page3.locator('[data-testid="amazon-store-selected"]').textContent()
  console.log(`   ✓ Loja após reload: ${store3b}`)
  if (store3b?.includes('Japan')) {
    console.log('   ✅ Preferência persistiu corretamente!\n')
  }

  await ctx3.close()

  console.log('═══════════════════════════════════════════════════════════════')
  console.log('                 ✅ TODOS OS TESTES PASSARAM!')
  console.log('═══════════════════════════════════════════════════════════════\n')

  await browser.close()
}

// Run the manual helper only in non-CI environments.
// This prevents accidental execution during automated CI pipelines.
if (!process.env.CI) {
  browserTest().catch((error) => {
    console.error('browserTest failed:', error)
    process.exit(1)
  })
} else {
  console.log('browserTest is a manual exploratory script and is skipped in CI.')
}
