<template>
  <q-page class="non-selectable" padding>
    <div class="flex items-center">
      <h1 class="text-primary text-left q-my-none">{{ userStore.me.display_name }}</h1>
      <q-space />
      <FollowRequestsIndicator class="q-mr-md" />
      <q-btn class="q-mr-sm" dense flat icon="share" @click="showShareDialog = true">
        <q-tooltip>{{ $t('share') }}</q-tooltip>
      </q-btn>
      <q-tabs v-model="activeTab" class="q-mr-sm" dense inline-label narrow-indicator>
        <q-tab :aria-label="$t('bookshelf')" class="q-pa-none" icon="auto_stories" name="shelf" />
        <q-tab :aria-label="$t('reading-stats')" class="q-pa-none" icon="bar_chart" name="stats" />
      </q-tabs>
      <ShelfDialog v-if="activeTab === 'shelf'" v-model="filter" :asc-desc="ascDesc" :sort-key="sortKey" @sort="onSort" />
    </div>

    <q-tab-panels v-model="activeTab" animated class="bg-transparent">
      <q-tab-panel class="q-pa-none" name="shelf">
        <TheShelf :books="filteredBooks" @import-completed="onImportCompleted" />
      </q-tab-panel>

      <q-tab-panel class="q-pa-none" name="stats">
        <ReadingStats v-if="userStore.me.username" :username="userStore.me.username" />
      </q-tab-panel>
    </q-tab-panels>

    <ShareButtons v-model="showShareDialog" />
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import ReadingStats from '@/components/profile/ReadingStats.vue'
import ShareButtons from '@/components/share/ShareButtons.vue'
import FollowRequestsIndicator from '@/components/social/FollowRequestsIndicator.vue'
import { useUserBookStore, useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import { computed, onMounted, ref } from 'vue'

const userStore = useUserStore()
const userBookStore = useUserBookStore()

const activeTab = ref('shelf')
const ascDesc = ref('desc')
const filter = ref('')
const sortKey = ref<string | number>('readIn')
const showShareDialog = ref(false)

const filteredBooks = computed(() => {
  const filtered =
    userStore.me.books?.filter(
      (book) => book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.toLowerCase().includes(filter.value.toLowerCase())
    ) || []
  return sortBooks(filtered, sortKey.value, ascDesc.value)
})

onMounted(() => {
  userBookStore.getUserBooks()
})

function onSort(label: string | number) {
  if (sortKey.value === label) {
    ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = label
    ascDesc.value = 'asc'
  }
}

function onImportCompleted() {
  userBookStore.getUserBooks()
}
</script>
