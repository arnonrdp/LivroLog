<template>
  <q-page padding>
    <!-- Empty state -->
    <div v-if="!isLoading && activityStore.feed.length === 0" class="empty-state text-center q-mt-xl">
      <q-icon color="grey-5" name="rss_feed" size="64px" />
      <p class="text-grey q-mt-md">{{ $t('feed.empty') }}</p>
      <q-btn color="primary" :label="$t('feed.find-people')" no-caps outline :to="'/people'" />
    </div>

    <!-- Activity groups -->
    <div v-else class="feed-container">
      <ActivityGroup
        v-for="group in activityStore.feed"
        :ref="(el) => setActivityRef(group.first_activity_id, el)"
        :key="`${group.user.id}-${group.type}-${group.date}`"
        :group="group"
        :initial-show-comments="shouldExpandComments(group.first_activity_id)"
        :is-highlighted="highlightedActivityId === group.first_activity_id"
        @comments-opened="handleCommentsOpened"
      />
    </div>

    <!-- Loading state -->
    <div v-if="isLoading" class="text-center q-mt-md">
      <q-spinner color="primary" size="40px" />
    </div>

    <!-- Infinite scroll -->
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
import ActivityGroup from '@/components/feed/ActivityGroup.vue'
import { useActivityStore, useNotificationStore } from '@/stores'
import type { ComponentPublicInstance } from 'vue'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const { t } = useI18n()
document.title = `LivroLog | ${t('feed.title')}`

const route = useRoute()
const router = useRouter()
const activityStore = useActivityStore()
const notificationStore = useNotificationStore()

const currentPage = ref(1)
const isLoading = ref(false)
const activityRefs = ref<Record<string, Element | null>>({})
const highlightedActivityId = ref<string | null>(null)

const hasMorePages = computed(() => activityStore.meta.current_page < activityStore.meta.last_page)

// Deep link parameters
const targetActivityId = computed(() => route.query.activity as string | undefined)
const shouldExpandCommentsParam = computed(() => route.query.expand === 'comments')

function setActivityRef(activityId: string, el: Element | ComponentPublicInstance | null) {
  activityRefs.value[activityId] = el ? (el as unknown as { $el: Element }).$el || (el as Element) : null
}

function shouldExpandComments(groupFirstActivityId: string): boolean {
  if (!targetActivityId.value || !shouldExpandCommentsParam.value) return false

  // Direct match first
  if (targetActivityId.value === groupFirstActivityId) return true

  // Then check if this group contains the target activity
  const targetGroupId = findActivityInFeed(targetActivityId.value)
  return targetGroupId === groupFirstActivityId
}

function handleCommentsOpened(activityId: string) {
  if (notificationStore.hasUnreadNotificationForActivity(activityId)) {
    notificationStore.postReadByActivity(activityId)
  }
}

function findActivityInFeed(activityId: string): string | null {
  // First try direct match with first_activity_id
  const directMatch = activityStore.feed.find(g => g.first_activity_id === activityId)
  if (directMatch) return directMatch.first_activity_id

  // If not found, search within activities of each group
  for (const group of activityStore.feed) {
    const found = group.activities?.some(a => a.id === activityId)
    if (found) return group.first_activity_id
  }

  return null
}

function scrollToActivity(activityId: string, retryCount = 0) {
  nextTick(() => {
    // Find the correct group ID (might differ from activityId)
    const groupId = findActivityInFeed(activityId) || activityId
    const el = activityRefs.value[groupId]

    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })

      // Highlight the activity after scroll completes
      setTimeout(() => {
        highlightedActivityId.value = groupId

        // Clear highlight after animation (2s to match new animation duration)
        setTimeout(() => {
          highlightedActivityId.value = null
        }, 2000)
      }, 500) // Wait for scroll to complete

      router.replace({ path: '/feed' })
    } else if (retryCount < 20) {
      // Retry if element not ready yet (DOM might still be updating)
      setTimeout(() => scrollToActivity(activityId, retryCount + 1), 150)
    }
  })
}

// Watch for route query changes (when clicking notification while already on /feed)
watch(
  () => route.query,
  (newQuery) => {
    if (newQuery.activity) {
      const activityId = newQuery.activity as string
      // If feed is already loaded, scroll immediately
      if (activityStore.feed.length > 0) {
        scrollToActivity(activityId)
      } else {
        // Otherwise, wait for feed to load
        loadFeed()
      }
    }
  }
)

onMounted(() => {
  loadFeed()
})

function loadFeed() {
  isLoading.value = true
  activityStore.getFeeds(1)
    .then(() => {
      if (targetActivityId.value) {
        scrollToActivity(targetActivityId.value)
      }
    })
    .finally(() => {
      isLoading.value = false
    })
}

function loadMore(_index: number, done: () => void) {
  if (!hasMorePages.value) {
    done()
    return
  }

  currentPage.value++
  activityStore.getFeeds(currentPage.value).finally(() => {
    done()
  })
}
</script>

<style scoped lang="sass">
.feed-container
  max-width: 600px
  margin: 0 auto

.empty-state
  padding: 3rem 1rem
</style>
