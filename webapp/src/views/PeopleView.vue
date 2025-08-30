<template>
  <q-page padding>
    <q-input v-model="filter" color="primary" debounce="300" dense flat :label="$t('search-for-people')">
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
    </q-input>

    <!-- Users Grid -->
    <div class="users-grid q-mt-md">
      <q-card v-for="user in displayedUsers" :key="user.id" class="full-height people-card">
        <router-link class="card-link" :to="user.username">
          <q-card-section class="text-center">
            <q-avatar class="bg-transparent" size="100px">
              <q-img v-if="user.avatar" alt="avatar" :src="user.avatar" />
              <q-icon v-else name="person" size="60px" />
            </q-avatar>
          </q-card-section>
          <q-card-section class="flex flex-center q-pt-none text-center">
            <div>
              <div class="text-weight-medium">
                {{ user.display_name || user.username }}
              </div>
              <div class="text-body2 text-grey">@{{ user.username }}</div>
            </div>
          </q-card-section>
        </router-link>

        <!-- Follow Stats -->
        <q-card-section v-if="user.followers_count !== undefined" class="text-center q-pt-none">
          <div class="row justify-center q-gutter-sm text-body2 text-grey">
            <div>
              <strong>{{ user.followers_count || 0 }}</strong>
              {{ $t('followers') }}
            </div>
            <div>
              <strong>{{ user.following_count || 0 }}</strong>
              {{ $t('following') }}
            </div>
          </div>
        </q-card-section>

        <!-- Follow Button -->
        <q-card-actions class="justify-center q-mt-auto">
          <q-btn
            v-if="!isSelf(user.id)"
            class="follow-btn"
            :color="getButtonColor(user)"
            dense
            :icon="getButtonIcon(user)"
            :label="getButtonLabel(user)"
            :loading="userLoadingStates[user.id] || false"
            :outline="getFollowingStatus(user.id, user.is_following) || user.has_pending_follow_request"
            :unelevated="!getFollowingStatus(user.id, user.is_following) && !user.has_pending_follow_request"
            @click.stop="toggleFollow(user.id, user.is_following, user.has_pending_follow_request)"
          >
            <q-tooltip v-if="followStore.isMutualFollow(user.id)">
              {{ $t('mutual-follow') }}
            </q-tooltip>
            <q-tooltip v-else-if="followStore.isFollowedBy(user.id)">
              {{ $t('follows-you') }}
            </q-tooltip>
          </q-btn>
        </q-card-actions>
      </q-card>
    </div>

    <!-- No Results Message -->
    <div v-if="displayedUsers.length === 0 && !isLoading" class="text-center q-mt-xl text-grey">
      {{ $t('no-one-found') }}
    </div>

    <!-- Loading Spinner -->
    <div v-if="isLoading" class="text-center q-mt-md">
      <q-spinner color="primary" size="40px" />
    </div>

    <!-- Infinite Scroll Trigger -->
    <q-infinite-scroll v-if="hasMorePages" :offset="250" @load="loadMore">
      <template v-slot:loading>
        <div class="text-center q-my-md">
          <q-spinner color="primary" size="40px" />
        </div>
      </template>
    </q-infinite-scroll>
  </q-page>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import { useFollowStore, useUserStore } from '@/stores'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
document.title = `LivroLog | ${t('people')}`

const followStore = useFollowStore()
const userStore = useUserStore()

const localFollowStatus = ref<Record<string, boolean>>({})
const userLoadingStates = ref<Record<string, boolean>>({})

const filter = ref('')
const currentPage = ref(1)
const pageSize = 20
const totalPages = ref(1)
const isLoading = ref(false)
const allUsers = ref<User[]>([])

const displayedUsers = computed(() => {
  if (filter.value) {
    const searchTerm = filter.value.toLowerCase()
    return allUsers.value.filter((user) => user.display_name?.toLowerCase().includes(searchTerm) || user.username?.toLowerCase().includes(searchTerm))
  }
  return allUsers.value
})

const hasMorePages = computed(() => currentPage.value < totalPages.value && !filter.value)

onMounted(() => {
  loadInitialUsers()
})

watch(filter, () => {
  if (filter.value) {
    // When filtering, load all users if not already loaded
    if (currentPage.value < totalPages.value) {
      loadAllUsers()
    }
  } else {
    // Reset to initial state when filter is cleared
    currentPage.value = 1
    allUsers.value = []
    loadInitialUsers()
  }
})

function isSelf(userId: string): boolean {
  return userStore.me.id === userId
}

function getFollowingStatus(userId: string, apiStatus?: boolean): boolean {
  // Priority: local state > API status > store state
  if (localFollowStatus.value[userId] !== undefined) {
    return localFollowStatus.value[userId]
  }
  if (apiStatus !== undefined) {
    return apiStatus
  }
  return followStore.isFollowing(userId)
}

function getButtonColor(user: User): string {
  if (user.has_pending_follow_request) {
    return 'orange'
  }
  return getFollowingStatus(user.id, user.is_following) ? 'grey-7' : 'primary'
}

function getButtonIcon(user: User): string {
  if (user.has_pending_follow_request) {
    return 'schedule'
  }
  return getFollowingStatus(user.id, user.is_following) ? 'person_remove' : 'person_add'
}

function getButtonLabel(user: User): string {
  if (user.has_pending_follow_request) {
    return t('remove-follow-request')
  }
  return getFollowingStatus(user.id, user.is_following) ? t('unfollow') : t('follow')
}

async function toggleFollow(userId: string, currentStatus?: boolean, hasPendingRequest?: boolean) {
  if (userLoadingStates.value[userId]) return

  const wasFollowing = getFollowingStatus(userId, currentStatus)

  // Set loading state for this specific user
  userLoadingStates.value[userId] = true

  try {
    if (wasFollowing || hasPendingRequest) {
      // Unfollow or remove pending request
      await followStore.deleteUserFollow(userId)
      localFollowStatus.value[userId] = false
    } else {
      // Follow or send request
      const response = await followStore.postUserFollow(userId)
      // For private profiles, the request might be pending
      localFollowStatus.value[userId] = response?.data?.status === 'accepted'
    }

    // Update the user in the list with the new status
    const userIndex = allUsers.value.findIndex((u) => u.id === userId)
    if (userIndex !== -1 && allUsers.value[userIndex]) {
      allUsers.value[userIndex].is_following = localFollowStatus.value[userId]
      allUsers.value[userIndex].has_pending_follow_request = false
    }
  } catch (error) {
    // Revert on error
    localFollowStatus.value[userId] = wasFollowing
    console.error('Error toggling follow:', error)
  } finally {
    // Clear loading state for this specific user
    userLoadingStates.value[userId] = false
  }
}

async function loadInitialUsers() {
  isLoading.value = true
  const params = {
    pagination: { page: 1, rowsPerPage: pageSize }
  }

  await userStore.getUsers(params).then(() => {
    allUsers.value = userStore.users
    totalPages.value = Math.ceil(userStore.meta.total / pageSize)
    isLoading.value = false
  })
}

async function loadMore(index: number, done: () => void) {
  if (currentPage.value >= totalPages.value) {
    done()
    return
  }

  currentPage.value++
  const params = {
    pagination: { page: currentPage.value, rowsPerPage: pageSize }
  }

  await userStore.getUsers(params).then(() => {
    allUsers.value = [...allUsers.value, ...userStore.users]
    done()
  })
}

async function loadAllUsers() {
  isLoading.value = true
  const promises = []

  // Load all remaining pages
  for (let page = currentPage.value + 1; page <= totalPages.value; page++) {
    const params = {
      pagination: { page, rowsPerPage: pageSize }
    }
    promises.push(userStore.getUsers(params).then(() => userStore.users))
  }

  Promise.all(promises).then((results) => {
    const newUsers = results.flat()
    allUsers.value = [...allUsers.value, ...newUsers]
    currentPage.value = totalPages.value
    isLoading.value = false
  })
}
</script>

<style scoped lang="sass">
.q-input
  margin: 0 auto
  max-width: 32rem

.users-grid
  display: grid
  grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr))
  gap: 8px
  justify-items: center

.people-card
  display: flex
  flex-direction: column
  height: 18rem
  transition: transform 0.2s ease, box-shadow 0.2s ease
  width: 14rem
  &:hover
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15)
    transform: translateY(-2px)

.card-link
  color: rgba(0, 0, 0, 0.85)
  display: block
  text-decoration: none
  &:hover
    color: rgba(0, 0, 0, 0.85)

.follow-btn
  min-width: 100px
</style>
