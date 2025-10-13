<template>
  <q-page class="non-selectable" padding>
    <div class="flex items-center">
      <h1 class="text-primary text-left q-my-none">{{ userStore.me.display_name }}</h1>
      <q-space />
      <FollowRequestsIndicator class="q-mr-md" />
      <q-btn class="q-mr-sm" dense flat icon="share" @click="showShareDialog = true">
        <q-tooltip>{{ $t('share') }}</q-tooltip>
      </q-btn>
      <ShelfDialog v-model="filter" :asc-desc="ascDesc" :sort-key="sortKey" @sort="onSort" />
    </div>
    <TheShelf :books="filteredBooks" @import-completed="onImportCompleted" />

    <ShareButtons v-model="showShareDialog" />
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import ShareButtons from '@/components/share/ShareButtons.vue'
import FollowRequestsIndicator from '@/components/social/FollowRequestsIndicator.vue'
import { useUserBookStore, useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import { computed, onMounted, ref } from 'vue'

const userStore = useUserStore()
const userBookStore = useUserBookStore()

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
