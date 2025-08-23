<template>
  <section class="flex justify-around">
    <figure v-for="book in books" v-show="onFilter(book.title)" :key="book.id">
      <!-- Private book indicator -->
      <div v-if="book.pivot?.is_private" class="private-indicator">
        <q-icon name="lock" size="14px">
          <q-tooltip anchor="top middle" class="bg-black" self="center middle">{{ $t('book-private-viewed-by-you') }}</q-tooltip>
        </q-icon>
      </div>

      <!-- Make the book image clickable -->
      <div class="book-cover" @click="openBookDialog(book)">
        <img v-if="book.thumbnail" :alt="`Cover of ${book.title}`" :src="book.thumbnail" />
        <img v-else :alt="`No cover available for ${book.title}`" src="@/assets/no_cover.jpg" />
      </div>

      <q-tooltip anchor="bottom middle" class="bg-black" self="center middle">
        {{ book.title }}
      </q-tooltip>
    </figure>
  </section>

  <!-- Book Dialog -->
  <BookDialog v-if="selectedBook" v-model="showBookDialog" :book="selectedBook" @read-date-updated="$emit('readDateUpdated')" />
</template>

<script setup lang="ts">
import type { Book, User } from '@/models'
import { ref } from 'vue'
import BookDialog from './BookDialog.vue'

defineProps<{
  books: User['books']
}>()

defineEmits(['readDateUpdated'])

const filter = ref('')
const selectedBook = ref<Book | null>(null)
const showBookDialog = ref(false)

function onFilter(title: Book['title']) {
  return title.toLowerCase().includes(filter.value.toLowerCase())
}

function openBookDialog(book: Book) {
  selectedBook.value = book
  showBookDialog.value = true
}
</script>

<style scoped lang="sass">
section
  background-image: url('@/assets/shelfleft.jpg'), url('@/assets/shelfright.jpg'), url('@/assets/shelfcenter.jpg')
  background-repeat: repeat-y, repeat-y, repeat
  background-position: top left, top right, 240px 0
  border-radius: 6px
  min-height: 302px
  padding: 0 3rem 1rem

section figure
  align-items: flex-end
  display: flex
  height: 143.5px
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
  cursor: pointer
  position: relative
  transition: transform 0.2s ease
  &:hover
    transform: scale(1.05)
    .amazon-buy-overlay
      opacity: 1

.amazon-buy-overlay
  position: absolute
  top: 0.5rem
  right: 0.5rem
  opacity: 0
  transition: opacity 0.2s ease
  z-index: 2

img
  height: 115px
</style>
