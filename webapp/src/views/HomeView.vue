<template>
  <q-page class="non-selectable" padding>
    <div class="flex items-center">
      <h1 class="text-primary text-left q-my-none">{{ userStore.me.display_name }}</h1>
      <q-space />
      <ShelfDialog v-model="filter" :asc-desc="ascDesc" :sort-key="sortKey" @sort="onSort" />
    </div>
    <TheShelf :books="filteredBooks" @readDateUpdated="handleReadDateUpdated" />
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import { useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import { computed, ref } from 'vue'

const userStore = useUserStore()

const ascDesc = ref('desc')
const sortKey = ref<string | number>('readIn')
const filter = ref('')

const books = computed(() => userStore.currentUser.books || [])

const filteredBooks = computed(() => {
  const filtered = books.value.filter(
    (book) => book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.toLowerCase().includes(filter.value.toLowerCase())
  )
  return sortBooks(filtered, sortKey.value, ascDesc.value)
})

// Router already loads user data with books via getUser() - no need for onMounted

function onSort(label: string | number) {
  if (sortKey.value === label) {
    ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = label
    ascDesc.value = 'asc'
  }
}

async function handleReadDateUpdated() {
  if (userStore.me.id) {
    await userStore.getUser(userStore.me.id)
  }
}
</script>
