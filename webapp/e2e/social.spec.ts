import { test, expect } from '@playwright/test'
import { LoginPage } from './pages/login.page'
import { HomePage } from './pages/home.page'
import { ProfilePage } from './pages/profile.page'
import { BookDialogPage } from './pages/book-dialog.page'
import { testUsers, testBooks } from './fixtures/test-data'

test.describe('Social Features', () => {
  test.describe('Following', () => {
    test('user can follow a public user', async ({ page, context }) => {
      const loginPage = new LoginPage(page)
      const profilePage = new ProfilePage(page)

      // Create first user (to be followed)
      const followedUser = {
        displayName: 'Followed User',
        email: `followed.${Date.now()}@test.com`,
        username: `followed_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.goto()
      await loginPage.register(followedUser)

      // Logout via settings/account
      await page.goto('/settings/account')
      await page.locator('[data-testid="logout-button"]').click()

      // Create second user (follower)
      const followerUser = {
        displayName: 'Follower User',
        email: `follower.${Date.now()}@test.com`,
        username: `follower_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.register(followerUser)

      // Go to followed user's profile
      await profilePage.goto(followedUser.username)
      await profilePage.follow()
      await profilePage.expectUnfollowButton()
    })

    test('user can unfollow a user', async ({ page }) => {
      const loginPage = new LoginPage(page)
      const profilePage = new ProfilePage(page)

      // Create users and follow
      const followedUser = {
        displayName: 'To Unfollow',
        email: `tounfollow.${Date.now()}@test.com`,
        username: `tounfollow_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.goto()
      await loginPage.register(followedUser)
      await page.locator('[data-testid="user-menu"]').click()
      await page.locator('[data-testid="logout-button"]').click()

      const followerUser = {
        displayName: 'Will Unfollow',
        email: `willunfollow.${Date.now()}@test.com`,
        username: `willunfollow_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.register(followerUser)

      // Follow and then unfollow
      await profilePage.goto(followedUser.username)
      await profilePage.follow()
      await profilePage.unfollow()
      await profilePage.expectFollowButton()
    })
  })

  test.describe('Private Profiles', () => {
    test('private profile requires follow request', async ({ page }) => {
      const loginPage = new LoginPage(page)
      const profilePage = new ProfilePage(page)
      const homePage = new HomePage(page)
      const bookDialog = new BookDialogPage(page)

      // Create private user
      const privateUser = {
        displayName: 'Private User',
        email: `privateuser.${Date.now()}@test.com`,
        username: `privateuser_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.goto()
      await loginPage.register(privateUser)

      // Add a book
      await homePage.searchBooks(testBooks.searchQuery)
      await homePage.clickOnBookResult(0)
      await bookDialog.addToLibrary()
      await bookDialog.close()

      // Set profile to private
      await profilePage.setPrivateProfile(true)

      // Logout via settings/account
      await page.goto('/settings/account')
      await page.locator('[data-testid="logout-button"]').click()

      // Create viewer user
      const viewerUser = {
        displayName: 'Viewer User',
        email: `viewer.${Date.now()}@test.com`,
        username: `viewer_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.register(viewerUser)

      // Try to view private profile
      await profilePage.goto(privateUser.username)
      await profilePage.expectBooksHidden()
    })

    test('follow request shows pending status', async ({ page }) => {
      const loginPage = new LoginPage(page)
      const profilePage = new ProfilePage(page)

      // Create private user
      const privateUser = {
        displayName: 'Private User',
        email: `private.${Date.now()}@test.com`,
        username: `private_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.goto()
      await loginPage.register(privateUser)
      await profilePage.setPrivateProfile(true)
      await page.locator('[data-testid="user-menu"]').click()
      await page.locator('[data-testid="logout-button"]').click()

      // Create requester
      const requesterUser = {
        displayName: 'Requester',
        email: `requester.${Date.now()}@test.com`,
        username: `requester_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.register(requesterUser)

      // Request to follow private user
      await profilePage.goto(privateUser.username)
      await profilePage.follow()
      await profilePage.expectPendingButton()
    })

    test('accepting follow request grants access to books', async ({ page, context }) => {
      const loginPage = new LoginPage(page)
      const profilePage = new ProfilePage(page)
      const homePage = new HomePage(page)
      const bookDialog = new BookDialogPage(page)

      // Create private user with a book
      const privateUser = {
        displayName: 'Private Owner',
        email: `privateowner.${Date.now()}@test.com`,
        username: `privateowner_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.goto()
      await loginPage.register(privateUser)

      await homePage.searchBooks(testBooks.searchQuery)
      await homePage.clickOnBookResult(0)
      await bookDialog.addToLibrary()
      await bookDialog.close()

      await profilePage.setPrivateProfile(true)
      await page.locator('[data-testid="user-menu"]').click()
      await page.locator('[data-testid="logout-button"]').click()

      // Create requester and send follow request
      const requesterUser = {
        displayName: 'Requester',
        email: `requester2.${Date.now()}@test.com`,
        username: `requester2_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.register(requesterUser)
      await profilePage.goto(privateUser.username)
      await profilePage.follow()
      await page.locator('[data-testid="user-menu"]').click()
      await page.locator('[data-testid="logout-button"]').click()

      // Login as private user and accept request
      await loginPage.login(privateUser.email, privateUser.password)
      await profilePage.goToFollowRequests()
      await profilePage.acceptFollowRequest()
      await page.locator('[data-testid="user-menu"]').click()
      await page.locator('[data-testid="logout-button"]').click()

      // Login as requester and check access
      await loginPage.login(requesterUser.email, requesterUser.password)
      await profilePage.goto(privateUser.username)
      await profilePage.expectBooksVisible()
    })
  })

  test.describe('Private Books', () => {
    test('private book is only visible to owner', async ({ page }) => {
      const loginPage = new LoginPage(page)
      const homePage = new HomePage(page)
      const bookDialog = new BookDialogPage(page)
      const profilePage = new ProfilePage(page)

      // Create user with private book
      const ownerUser = {
        displayName: 'Book Owner',
        email: `bookowner.${Date.now()}@test.com`,
        username: `bookowner_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.goto()
      await loginPage.register(ownerUser)

      // Add book and mark as private
      await homePage.searchBooks(testBooks.searchQuery)
      await homePage.clickOnBookResult(0)
      await bookDialog.addToLibrary()
      await bookDialog.setPrivate(true)
      await bookDialog.close()

      // Logout via settings/account
      await page.goto('/settings/account')
      await page.locator('[data-testid="logout-button"]').click()

      // Create viewer
      const viewerUser = {
        displayName: 'Viewer',
        email: `viewer2.${Date.now()}@test.com`,
        username: `viewer2_${Date.now()}`,
        password: 'TestPassword123!',
      }
      await loginPage.register(viewerUser)

      // Follow the owner (public profile)
      await profilePage.goto(ownerUser.username)
      await profilePage.follow()

      // Private book should not be visible even to followers
      await expect(page.locator('[data-testid="profile-books"]')).toBeEmpty()
    })
  })
})
