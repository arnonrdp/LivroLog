<template>
  <q-page class="non-selectable" padding>
    <div class="flex items-center">
      <h1 class="text-primary text-left q-my-none">{{ userStore.me.shelf_name || userStore.me.display_name }}</h1>
      <q-space />
      <FollowRequestsIndicator class="q-mr-md" />
      <q-btn class="q-mr-sm" dense flat icon="share" @click="showShareDialog = true">
        <q-tooltip>{{ $t('share') }}</q-tooltip>
      </q-btn>
      <q-tabs v-model="activeTab" class="q-mr-sm" dense inline-label narrow-indicator>
        <q-tab :aria-label="$t('bookshelf')" class="q-pa-none" icon="auto_stories" name="shelf" />
        <q-tab :aria-label="$t('reading-stats')" class="q-pa-none" icon="bar_chart" name="stats" />
      </q-tabs>
      <ShelfDialog
        v-if="activeTab === 'shelf'"
        v-model="filter"
        :asc-desc="ascDesc"
        custom-sort
        :show-no-tag="showNoTag"
        :sort-key="sortKey"
        :visible-tags="visibleTags"
        @sort="onSort"
        @update:show-no-tag="showNoTag = $event"
        @update:visible-tags="visibleTags = $event"
      />
    </div>

    <q-tab-panels v-model="activeTab" animated class="bg-transparent">
      <q-tab-panel class="q-pa-none" name="shelf">
        <TheShelf
          :books="filteredBooks"
          reorderable
          :shelf-texture="userStore.me.shelf_texture"
          :show-tag-dots="sortKey === 'tags'"
          @import-completed="onImportCompleted"
          @reorder="onReorder"
        />
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
import type { Tag } from '@/models'
import { useTagStore, useUserBookStore, useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import { LocalStorage } from 'quasar'
import { computed, onMounted, ref, watch } from 'vue'

const userStore = useUserStore()
const userBookStore = useUserBookStore()
const tagStore = useTagStore()

// The chosen sort survives reloads; the custom order itself lives in the API (pivot.position)
const savedSort = LocalStorage.getItem<{ key: string; dir: string }>('shelfSort')

const activeTab = ref('shelf')
const ascDesc = ref(savedSort?.dir || 'desc')
const filter = ref('')
const sortKey = ref<string | number>(savedSort?.key || 'readIn')
const showShareDialog = ref(false)
const visibleTags = ref<string[] | null>(null) // null = show all, [] = show none tagged
const showNoTag = ref(true)

// Extended Book type with tags
interface BookWithTags {
  id: string
  title: string
  authors?: string
  tags?: Tag[]
  [key: string]: unknown
}

const filteredBooks = computed(() => {
  let books = userStore.me.books || []

  // Text filter
  if (filter.value) {
    const searchLower = filter.value.toLowerCase()
    books = books.filter((book) => book.title.toLowerCase().includes(searchLower) || book.authors?.toLowerCase().includes(searchLower))
  }

  // Tag filter - only apply if we have tags defined AND filter is active
  // Filter is active when: visibleTags is not null (explicit selection) OR showNoTag is false
  const isFilterActive = visibleTags.value !== null || !showNoTag.value

  if (tagStore.tags.length > 0 && isFilterActive) {
    books = books.filter((book) => {
      const bookWithTags = book as BookWithTags
      const bookTags = bookWithTags.tags || tagStore.getTagsForBook(book.id) || []
      const hasNoTags = bookTags.length === 0

      // If book has no tags, check showNoTag
      if (hasNoTags) {
        return showNoTag.value
      }

      // If visibleTags is null, show all tagged books
      if (visibleTags.value === null) {
        return true
      }

      // If visibleTags is empty array, hide all tagged books
      if (visibleTags.value.length === 0) {
        return false
      }

      // Check if book has any of the visible tags
      return bookTags.some((tag) => visibleTags.value!.includes(tag.id))
    })
  }

  return sortBooks(books, sortKey.value, ascDesc.value)
})

watch([sortKey, ascDesc], ([key, dir]) => LocalStorage.set('shelfSort', { key, dir }))

onMounted(() => {
  userBookStore.getUserBooks()
  tagStore.getTags()
})

function onSort(label: string | number) {
  // Custom has no direction: it is exactly the order the books were dragged into
  if (label === 'custom') {
    sortKey.value = label
    ascDesc.value = 'asc'
  } else if (sortKey.value === label) {
    ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = label
    ascDesc.value = 'asc'
  }
}

// Dragging always starts from what is on screen, so a drag after sorting by date
// replaces the previous custom order with the date order plus this move.
function onReorder(fromId: string, toId: string) {
  const ids = sortBooks(userStore.me.books || [], sortKey.value, ascDesc.value).map((book) => book.id)
  const to = ids.indexOf(toId)
  ids.splice(ids.indexOf(fromId), 1)
  ids.splice(to, 0, fromId)

  sortKey.value = 'custom'
  ascDesc.value = 'asc'
  userBookStore.putUserBooksOrder(ids)
}

function onImportCompleted() {
  userBookStore.getUserBooks()
}
</script>
