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
      <ActivityGroup v-for="group in activityStore.feed" :key="`${group.user.id}-${group.type}-${group.date}`" :group="group" />
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
import { useActivityStore } from '@/stores'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
document.title = `LivroLog | ${t('feed.title')}`

const activityStore = useActivityStore()

const currentPage = ref(1)
const isLoading = ref(false)

const hasMorePages = computed(() => activityStore.meta.current_page < activityStore.meta.last_page)

onMounted(() => {
  loadFeed()
})

function loadFeed() {
  isLoading.value = true
  activityStore.getFeeds(1).finally(() => {
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
