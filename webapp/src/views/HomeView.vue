<template>
  <q-page class="non-selectable" padding>
    <div class="flex items-center">
      <h1 class="text-primary text-left q-my-none">{{ userStore.me.display_name }}</h1>
      <q-space />
      <FollowRequestsIndicator class="q-mr-md" />
      <ShelfDialog v-model="filter" :asc-desc="ascDesc" :sort-key="sortKey" @sort="onSort" />
    </div>
    <TheShelf :books="filteredBooks" @readDateUpdated="handleReadDateUpdated" />
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import FollowRequestsIndicator from '@/components/social/FollowRequestsIndicator.vue'
import { useAuthStore, useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import { computed, onMounted, ref } from 'vue'

const authStore = useAuthStore()
const userStore = useUserStore()

const ascDesc = ref('desc')
const filter = ref('')
const sortKey = ref<string | number>('readIn')

const filteredBooks = computed(() => {
  const filtered =
    userStore.me.books?.filter(
      (book) => book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.toLowerCase().includes(filter.value.toLowerCase())
    ) || []
  return sortBooks(filtered, sortKey.value, ascDesc.value)
})

onMounted(() => {
  authStore.getMe()
})

function onSort(label: string | number) {
  if (sortKey.value === label) {
    ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = label
    ascDesc.value = 'asc'
  }
}

async function handleReadDateUpdated() {
  // Refresh the current user data to get updated books
  await authStore.getMe()
}
</script>
