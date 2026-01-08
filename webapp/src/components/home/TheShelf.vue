<template>
  <!-- Empty state when user has no books and is viewing their own shelf -->
  <EmptyShelfState v-if="!userIdentifier && (!books || books.length === 0)" @import="handleImport" />

  <!-- Regular shelf display -->
  <section v-else class="flex justify-around">
    <figure v-for="book in books" v-show="onFilter(book.title)" :key="book.id" data-testid="library-book">
      <!-- Private book indicator removed - privacy info available in BookDialog -->

      <!-- Make the book image clickable only for authenticated users on other shelves -->
      <div :class="['book-cover', { clickable: canOpenBookDialog }]" @click="openBookDialog(book)">
        <img v-if="book.thumbnail" :alt="`Cover of ${book.title}`" :src="book.thumbnail" />
        <img v-else :alt="`No cover available for ${book.title}`" src="@/assets/no_cover.jpg" />
      </div>

      <q-tooltip anchor="bottom middle" class="bg-black" self="center middle">
        {{ book.title }}
      </q-tooltip>
    </figure>
  </section>

  <!-- Book Dialog -->
  <BookDialog v-model="showBookDialog" :book-id="selectedBookId" :user-identifier="props.userIdentifier" />

  <!-- GoodReads Import Dialog -->
  <GoodReadsImportDialog v-model="showImportDialog" @import-completed="onImportCompleted" />
</template>

<script setup lang="ts">
import type { Book, User } from '@/models'
import { useAuthStore } from '@/stores'
import { computed, ref } from 'vue'
import BookDialog from './BookDialog.vue'
import EmptyShelfState from './EmptyShelfState.vue'
import GoodReadsImportDialog from './GoodReadsImportDialog.vue'

const props = defineProps<{
  books?: User['books']
  userIdentifier?: string // if provided, means viewing another user's shelf
}>()

const emit = defineEmits<{
  'import-completed': []
}>()

const authStore = useAuthStore()

const filter = ref('')
const showBookDialog = ref(false)
const showImportDialog = ref(false)
const selectedBookId = ref<string | undefined>()

const canOpenBookDialog = computed(() => {
  // Users can always open books from their own shelf
  // For other users' shelves, only authenticated users can open the dialog
  return !props.userIdentifier || authStore.isAuthenticated
})

function onFilter(title: Book['title']) {
  return title.toLowerCase().includes(filter.value.toLowerCase())
}

function openBookDialog(book: Book) {
  if (!canOpenBookDialog.value) return

  selectedBookId.value = book.id
  showBookDialog.value = true
}

function handleImport() {
  showImportDialog.value = true
}

function onImportCompleted() {
  emit('import-completed')
}
</script>

<style scoped lang="sass">
section
  background-image: url('@/assets/textures/shelfleft.jpg'), url('@/assets/textures/shelfright.jpg'), url('@/assets/textures/shelfcenter.jpg')
  background-repeat: repeat-y, repeat-y, repeat
  background-position: top left, top right, 240px 0
  border-radius: 6px
  min-height: 302px
  padding: 0 3rem 2.2rem

section figure
  align-items: flex-end
  display: flex
  height: 146px
  margin: 0 1.5rem
  position: relative

  & .private-indicator
    align-items: center
    background: rgba(0, 0, 0, 0.7)
    border-radius: 50%
    color: red
    display: flex
    justify-content: center
    padding: 0.25rem
    position: absolute
    right: -0.5rem
    top: 1rem
    z-index: 1

.book-cover
  position: relative
  transition: transform 0.2s ease

  &.clickable
    cursor: pointer
    &:hover
      transform: scale(1.05)

  &:not(.clickable)
    cursor: default

img
  height: 115px
</style>
