<template>
  <q-page class="non-selectable" padding>
    <div v-if="userStore.isLoading" class="text-center q-py-xl">
      <q-spinner color="primary" size="3em" />
      <div class="text-grey q-mt-md">{{ $t('loading') }}</div>
    </div>

    <div v-else-if="!person.id" class="text-center q-py-xl">
      <q-icon class="q-mb-md" color="grey" name="person_off" size="6em" />
      <div class="text-h6 text-grey">{{ $t('not-found', 'Usuário não encontrado') }}</div>
    </div>

    <div v-else>
      <!-- Private Profile Message -->
      <div v-if="isPrivateAndNotAccessible" class="text-center q-py-xl" data-testid="private-profile-message">
        <q-icon class="q-mb-md" color="grey" name="lock" size="6em" />
        <div class="text-h5 q-mb-md">{{ $t('private-profile') }}</div>
        <div class="text-body1 text-grey q-mb-lg">{{ $t('private-profile-message') }}</div>

        <!-- Follow Button for Private Profile -->
        <q-btn
          v-if="showFollowButton"
          class="q-mt-md"
          :color="getButtonColor()"
          :data-testid="hasPendingRequest ? 'pending-button' : isFollowing ? 'unfollow-button' : 'follow-button'"
          :icon="getButtonIcon()"
          :label="getButtonLabel()"
          no-caps
          :outline="hasPendingRequest || isFollowing"
          @click="handleFollowAction()"
        />
      </div>

      <!-- Public Profile or Following -->
      <div v-else>
        <div class="flex items-center">
          <h1 class="text-primary text-left q-my-none">{{ person.shelf_name || person.display_name }}</h1>
          <q-space />
          <q-btn-toggle
            v-model="activeTab"
            class="q-mr-sm"
            :options="tabOptions"
            rounded
            toggle-color="primary"
            unelevated
          />
          <ShelfDialog v-if="activeTab === 'shelf'" v-model="filter" :asc-desc="ascDesc" :sort-key="sortKey" @sort="onSort" />
        </div>

        <q-tab-panels v-model="activeTab" animated class="bg-transparent">
          <q-tab-panel name="shelf" class="q-pa-none">
            <TheShelf :books="filteredBooks" data-testid="profile-books" :user-identifier="person.username" />
          </q-tab-panel>

          <q-tab-panel name="stats" class="q-pa-none">
            <ReadingStats v-if="person.username" :username="person.username" />
          </q-tab-panel>
        </q-tab-panels>
      </div>
    </div>

    <q-dialog v-model="showUnfollowDialog" persistent>
      <q-card>
        <q-card-section>
          <div class="text-h6">{{ $t('confirm') }}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          {{ $t('unfollow-confirmation', { name: person?.display_name }) }}
        </q-card-section>

        <q-card-actions align="right">
          <q-btn color="primary" flat :label="$t('cancel')" @click="showUnfollowDialog = false" />
          <q-btn color="negative" flat :label="$t('confirm')" @click="confirmUnfollow" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import ReadingStats from '@/components/profile/ReadingStats.vue'
import { useAuthStore, useFollowStore, useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

defineProps<{
  username?: string
}>()

const route = useRoute()
const { t } = useI18n()

const authStore = useAuthStore()
const userStore = useUserStore()
const followStore = useFollowStore()

const activeTab = ref('shelf')
const ascDesc = ref('desc')
const filter = ref('')
const showUnfollowDialog = ref(false)
const sortKey = ref<string | number>('readIn')

const tabOptions = computed(() => [
  { value: 'shelf', icon: 'auto_stories', attrs: { 'aria-label': t('bookshelf') } },
  { value: 'stats', icon: 'bar_chart', attrs: { 'aria-label': t('reading-stats') } }
])

const person = computed(() => {
  const username = route.params.username as string
  // If viewing own profile, use userStore.me, otherwise use userStore.user
  // Use case-insensitive comparison for usernames
  if (username?.toLowerCase() === userStore.me?.username?.toLowerCase()) {
    return userStore.me
  }
  return userStore.user
})

const filteredBooks = computed(() => {
  // Ensure books is an array before filtering
  const books = Array.isArray(person.value?.books) ? person.value.books : []
  const filtered = books.filter(
    (book) => book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.toLowerCase().includes(filter.value.toLowerCase())
  )
  return sortBooks(filtered, sortKey.value, ascDesc.value)
})

const isPrivateAndNotAccessible = computed(() => {
  // Show private profile message if:
  // 1. User is private AND
  // 2. No books are loaded (meaning the current user doesn't have access)
  const books = person.value?.books
  return person.value?.is_private && (!Array.isArray(books) || books.length === 0)
})

const canSendFollowRequest = computed(() => {
  return authStore.isAuthenticated && person.value?.id && userStore.me?.id !== person.value.id && !followStore.isFollowing(person.value.id)
})

const hasPendingRequest = computed(() => {
  return person.value?.has_pending_follow_request || false
})

const isFollowing = computed(() => {
  return person.value?.is_following || followStore.isFollowing(person.value?.id || '')
})

const showFollowButton = computed(() => {
  return (
    authStore.isAuthenticated &&
    person.value?.id &&
    userStore.me?.id !== person.value.id &&
    (canSendFollowRequest.value || hasPendingRequest.value || isFollowing.value)
  )
})

// Update Open Graph meta tags for social sharing
function updateMetaTags() {
  // Remove existing OG tags
  const existingTags = document.querySelectorAll('meta[property^="og:"], meta[name^="twitter:"]')
  existingTags.forEach((tag) => tag.remove())

  // Update canonical URL for this specific page
  const canonicalTag = document.querySelector('link[rel="canonical"]')
  if (canonicalTag && 'href' in canonicalTag) {
    canonicalTag.href = window.location.href
  }

  if (person.value?.display_name) {
    const booksCount = Array.isArray(person.value.books) ? person.value.books.length : 0
    const shelfName = person.value.shelf_name || person.value.display_name
    const currentUrl = window.location.href
    const imageUrl = userStore.getShelfImageUrl(person.value.id)
    const description =
      booksCount > 0
        ? `Veja os ${booksCount} livros favoritos do ${person.value.display_name}`
        : `Biblioteca do ${person.value.display_name} no LivroLog`

    // Create and append new meta tags
    const metaTags = [
      { property: 'og:title', content: `${shelfName} - LivroLog` },
      { property: 'og:description', content: description },
      { property: 'og:image', content: imageUrl },
      { property: 'og:url', content: currentUrl },
      { property: 'og:type', content: 'profile' },
      { property: 'og:site_name', content: 'LivroLog' },
      { name: 'twitter:card', content: 'summary_large_image' },
      { name: 'twitter:title', content: `${shelfName} - LivroLog` },
      { name: 'twitter:description', content: description },
      { name: 'twitter:image', content: imageUrl }
    ]

    metaTags.forEach((tagData) => {
      const meta = document.createElement('meta')
      if (tagData.property) {
        meta.setAttribute('property', tagData.property)
      } else if (tagData.name) {
        meta.setAttribute('name', tagData.name)
      }
      meta.setAttribute('content', tagData.content)
      document.head.appendChild(meta)
    })

    // Update description meta tag
    let descriptionTag = document.querySelector('meta[name="description"]')
    if (!descriptionTag) {
      descriptionTag = document.createElement('meta')
      descriptionTag.setAttribute('name', 'description')
      document.head.appendChild(descriptionTag)
    }
    descriptionTag.setAttribute('content', description)
  }
}

function getButtonColor() {
  if (hasPendingRequest.value) return 'orange'
  if (isFollowing.value) return 'grey'
  return 'primary'
}

function getButtonIcon() {
  if (hasPendingRequest.value) return 'schedule'
  if (isFollowing.value) return 'person_remove'
  return 'person_add'
}

function getButtonLabel() {
  if (hasPendingRequest.value) return t('remove-follow-request')
  if (isFollowing.value) return t('unfollow')
  return t('send-follow-request')
}

function handleFollowAction() {
  if (isFollowing.value) {
    unfollowUser()
  } else if (hasPendingRequest.value) {
    removeFollowRequest()
  } else {
    sendFollowRequest()
  }
}

// Update document title and meta tags when person changes
watch(
  person,
  (newPerson) => {
    document.title = newPerson?.display_name ? `LivroLog | ${newPerson.display_name}` : 'LivroLog'
    updateMetaTags()
  },
  { immediate: true }
)

// Watch for route changes to load different user profiles
watch(
  () => route.params.username,
  async (newUsername) => {
    if (newUsername && typeof newUsername === 'string') {
      if (newUsername.toLowerCase() !== userStore.me?.username?.toLowerCase()) {
        // Clear previous user data and load new user
        userStore.$patch({ _user: {} })
        await userStore.getUser(newUsername)
      }
    }
  }
)

onMounted(async () => {
  const username = route.params.username as string
  if (username) {
    // If viewing own profile, don't need to call getUser as we already have userStore.me
    if (username.toLowerCase() !== userStore.me?.username?.toLowerCase()) {
      // Clear previous user data to avoid showing stale data
      userStore.$patch({ _user: {} })
      await userStore.getUser(username)
    }
  }
})

onUnmounted(() => {
  followStore.clearFollowStatus()

  // Clear user data to avoid showing stale data in other views
  userStore.$patch({ _user: {} })

  // Clean up meta tags
  const metaTags = document.querySelectorAll('meta[property^="og:"], meta[name^="twitter:"]')
  metaTags.forEach((tag) => tag.remove())
})

function onSort(label: string | number) {
  if (sortKey.value === label) {
    ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = label
    ascDesc.value = 'asc'
  }
}

async function sendFollowRequest() {
  if (!person.value?.id) return

  const response = await followStore.postUserFollow(person.value.id)

  // Always refresh user data to get updated status
  if (response.success && person.value.username !== userStore.me?.username) {
    await userStore.getUser(person.value.username)
  }
}

async function removeFollowRequest() {
  if (!person.value?.id) return

  // Use the same unfollow endpoint to remove the pending request
  await followStore.deleteUserFollow(person.value.id)

  // Refresh user data to get updated request status
  if (person.value.username !== userStore.me?.username) {
    await userStore.getUser(person.value.username)
  }
}

async function unfollowUser() {
  if (!person.value?.id) return

  // Show confirmation dialog for unfollow
  showUnfollowDialog.value = true
}

async function confirmUnfollow() {
  if (!person.value?.id) return

  showUnfollowDialog.value = false

  await followStore.deleteUserFollow(person.value.id)

  // Refresh user data to get updated follow status
  if (person.value.username !== userStore.me?.username) {
    await userStore.getUser(person.value.username)
  }
}
</script>
