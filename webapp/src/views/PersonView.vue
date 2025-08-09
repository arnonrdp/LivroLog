<template>
  <q-page class="non-selectable" padding>
    <div v-if="peopleStore.isLoading" class="text-center q-py-xl">
      <q-spinner color="primary" size="3em" />
      <div class="text-grey q-mt-md">{{ $t('loading') }}</div>
    </div>

    <div v-else-if="!person.id" class="text-center q-py-xl">
      <q-icon class="q-mb-md" color="grey" name="person_off" size="6em" />
      <div class="text-h6 text-grey">{{ $t('not-found', 'Usuário não encontrado') }}</div>
    </div>

    <div v-else>
      <!-- Private Profile Message -->
      <div v-if="person.is_private && !person.books" class="text-center q-py-xl">
        <q-icon class="q-mb-md" color="grey" name="lock" size="6em" />
        <div class="text-h5 q-mb-md">{{ $t('private-profile') }}</div>
        <div class="text-body1 text-grey q-mb-lg">{{ $t('private-profile-message') }}</div>
      </div>

      <!-- Public Profile or Following -->
      <div v-else>
        <!-- Mobile Layout -->
        <div class="lt-md">
          <div class="flex items-center q-mb-md">
            <h1 class="text-primary text-left q-my-none">
              {{ person.shelf_name || person.display_name }}
            </h1>
            <q-space />
            <ShelfDialog v-model="filter" :asc-desc="ascDesc" :sort-key="sortKey" @sort="onSort" />
          </div>

          <TheShelf :books="filteredBooks" @emitAddID="addBook" />
        </div>

        <!-- Desktop Layout -->
        <div class="gt-sm">
          <div class="flex items-center q-mb-md">
            <h1 class="text-primary text-left q-my-none">{{ person.shelf_name || person.display_name }}</h1>
            <q-space />
            <ShelfDialog v-model="filter" :asc-desc="ascDesc" :sort-key="sortKey" @sort="onSort" />
          </div>

          <TheShelf :books="filteredBooks" @emitAddID="addBook" />
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import type { Book, User } from '@/models'
import { useBookStore, useFollowStore, usePeopleStore } from '@/stores'
import { sortBooks } from '@/utils'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const peopleStore = usePeopleStore()
const bookStore = useBookStore()
const followStore = useFollowStore()

const ascDesc = ref('desc')
const sortKey = ref<string | number>('readIn')
const filter = ref('')
const person = ref({} as User)

const filteredBooks = computed(() => {
  const filtered = (person.value.books || []).filter(
    (book: Book) => book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.toLowerCase().includes(filter.value.toLowerCase())
  )
  return sortBooks(filtered, sortKey.value, ascDesc.value)
})

peopleStore.$subscribe((_mutation, state) => {
  person.value = state._person
  document.title = person.value.display_name ? `LivroLog | ${person.value.display_name}` : 'LivroLog'
})

onMounted(async () => {
  const username = route.params.username as string
  if (username) {
    await peopleStore.getUserByIdentifier(username)
  }
})

onUnmounted(() => {
  followStore.clearFollowStatus()
})

function onSort(label: string | number) {
  if (sortKey.value === label) {
    ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = label
    ascDesc.value = 'asc'
  }
}

async function addBook(book: Book) {
  book = { ...book, addedIn: Date.now(), readIn: '' }
  await bookStore.postBook(book)
}
</script>
