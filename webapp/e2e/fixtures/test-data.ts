// Test data factory for E2E tests
// Uses faker for realistic data generation
// Note: Username is auto-generated from displayName (lowercase, no spaces)
// Backend has 20-char username limit, so displayName must be short

import { faker } from '@faker-js/faker'

// Factory function to generate unique user data
// Username is auto-generated from displayName, keep under 20 chars
export function createTestUser(prefix: string = 'usr') {
  const firstName = faker.person.firstName().slice(0, 8)
  const timestamp = Date.now()
  const shortId = String(timestamp).slice(-4)
  const displayName = `${prefix}${firstName}${shortId}`.slice(0, 20)

  return {
    displayName,
    email: faker.internet.email({ firstName: prefix, lastName: String(timestamp) }),
    username: displayName.toLowerCase(),
    password: 'TestPassword123!'
  }
}

// Pre-generated test users (regenerated on import)
export const testUsers = {
  primary: createTestUser('usr'),
  secondary: createTestUser('sec'),
  privateUser: createTestUser('prv')
}

export const testBooks = {
  searchQuery: 'Dom Casmurro',
  isbn: '9788525406958'
}

// Factory function to generate review data
export function createTestReview() {
  return {
    title: faker.lorem.sentence({ min: 2, max: 5 }),
    content: faker.lorem.paragraph({ min: 1, max: 3 }),
    rating: faker.number.int({ min: 1, max: 5 })
  }
}

// Pre-generated test review (for backwards compatibility)
export const testReview = {
  title: 'Great Read',
  content: 'This is a fantastic book that I thoroughly enjoyed reading.',
  rating: 5
}
