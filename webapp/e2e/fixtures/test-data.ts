// Test data factory for E2E tests
// Each call generates unique data with timestamp
// Note: Username is auto-generated from displayName (lowercase, no spaces)
// Backend has 20-char username limit, so displayName must be short

const ts1 = String(Date.now()).slice(-8)
const ts2 = String(Date.now() + 1).slice(-8)
const ts3 = String(Date.now() + 2).slice(-8)

export const testUsers = {
  primary: {
    displayName: `Usr${ts1}`,
    email: `testuser.e2e.${Date.now()}@test.com`,
    username: `usr${ts1}`,
    password: 'TestPassword123!',
  },
  secondary: {
    displayName: `Sec${ts2}`,
    email: `secondary.e2e.${Date.now()}@test.com`,
    username: `sec${ts2}`,
    password: 'SecondaryPassword123!',
  },
  privateUser: {
    displayName: `Prv${ts3}`,
    email: `private.e2e.${Date.now()}@test.com`,
    username: `prv${ts3}`,
    password: 'PrivatePassword123!',
  },
}

// Factory function to generate unique user data
// Username is auto-generated from displayName, keep under 20 chars
export function createTestUser(prefix: string = 'usr') {
  const timestamp = Date.now()
  const shortId = String(timestamp).slice(-8)
  return {
    displayName: `${prefix}${shortId}`,
    email: `${prefix}.${timestamp}@test.com`,
    username: `${prefix}${shortId}`.toLowerCase(),
    password: 'TestPassword123!',
  }
}

export const testBooks = {
  searchQuery: 'Dom Casmurro',
  isbn: '9788525406958',
}

export const testReview = {
  title: 'Great Read',
  content: 'This is a fantastic book that I thoroughly enjoyed reading.',
  rating: 5,
}
