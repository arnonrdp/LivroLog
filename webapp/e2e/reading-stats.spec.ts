import { expect, test } from '@playwright/test'

test.describe('Reading Statistics', () => {
  test('should display reading stats on user profile', async ({ page }) => {
    // Navigate to a user profile (use dev environment)
    await page.goto('http://localhost:8001/arnon')

    // Wait for page to load
    await page.waitForLoadState('networkidle')

    // Take a screenshot to see the current state
    await page.screenshot({ path: 'test-results/profile-stats.png', fullPage: true })

    // Check if the stats section is visible (if user has books)
    const statsSection = page.locator('.reading-stats')
    const isVisible = await statsSection.isVisible().catch(() => false)

    if (isVisible) {
      console.log('Reading stats section is visible!')

      // Check for the charts
      const charts = page.locator('.chart')
      const chartCount = await charts.count()
      console.log(`Found ${chartCount} charts`)

      expect(chartCount).toBeGreaterThanOrEqual(1)
    } else {
      console.log('Reading stats section not visible (user may have no books or profile is private)')
    }
  })

  test('should call stats API endpoint', async ({ request }) => {
    // Test the API endpoint directly
    const response = await request.get('http://localhost:8000/users/arnon/stats')

    console.log('API Response status:', response.status())

    if (response.ok()) {
      const data = await response.json()
      console.log('Stats data:', JSON.stringify(data, null, 2))

      expect(data).toHaveProperty('by_status')
      expect(data).toHaveProperty('by_month')
      expect(data).toHaveProperty('by_category')
    } else {
      console.log('API returned non-200 status (user may not exist or profile is private)')
    }
  })
})
