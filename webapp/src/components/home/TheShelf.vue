<template>
  <section class="flex justify-around">
    <figure v-for="book in books" v-show="onFilter(book.title)" :key="book.id">
      <q-btn
        v-if="selfUser"
        color="negative"
        icon="close"
        round
        size="sm"
        style="right: -1rem; top: 1rem"
        @click.stop="$emit('emitRemoveID', book.id)"
      />
      <q-btn v-else color="primary" icon="add" round size="sm" style="right: -1rem; top: 1rem" @click.stop="$emit('emitAddID', book)" />

      <!-- Make the book image clickable -->
      <div class="book-cover" @click="openBookDialog(book)">
        <img v-if="book.thumbnail" :alt="`Cover of ${book.title}`" :src="book.thumbnail" />
        <img v-else :alt="`No cover available for ${book.title}`" src="@/assets/no_cover.jpg" />
      </div>

      <q-tooltip anchor="bottom middle" class="bg-black" self="center middle">
        {{ book.title }}
        <br />
        <small>{{ $t('click-to-review') }}</small>
      </q-tooltip>
    </figure>
  </section>

  <!-- Book Dialog -->
  <BookDialog v-if="selectedBook" v-model="showBookDialog" :book="selectedBook" @read-date-updated="$emit('readDateUpdated')" />
</template>

<script setup lang="ts">
import type { Book, User } from '@/models'
import { useAuthStore } from '@/stores'
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import BookDialog from './BookDialog.vue'

defineProps<{
  books: User['books']
}>()

defineEmits(['emitAddID', 'emitReadDate', 'emitRemoveID', 'readDateUpdated'])

const authStore = useAuthStore()
const route = useRoute()

const filter = ref('')
const selectedBook = ref<Book | null>(null)
const selfUser = ref(!route.params.username || route.params.username === authStore.user.username)
const showBookDialog = ref(false)

function onFilter(title: Book['title']) {
  return title.toLowerCase().includes(filter.value.toLowerCase())
}

function openBookDialog(book: Book) {
  selectedBook.value = book
  showBookDialog.value = true
}
</script>

<style scoped>
section {
  background-image: url('@/assets/shelfleft.jpg'), url('@/assets/shelfright.jpg'), url('@/assets/shelfcenter.jpg');
  background-repeat: repeat-y, repeat-y, repeat;
  background-position:
    top left,
    top right,
    240px 0;
  border-radius: 6px;
  min-height: 302px;
  padding: 0 3rem 1rem;
}

section figure {
  align-items: flex-end;
  display: flex;
  height: 143.5px;
  margin: 0 1.5rem;
  max-width: 80px;
  position: relative;
}

figure button {
  opacity: 0;
  position: absolute;
  visibility: hidden;
  z-index: 1;
}

figure button:hover,
figure:hover button {
  opacity: 1;
  transition: 0.5s;
  visibility: visible;
}

.book-cover {
  cursor: pointer;
  transition: transform 0.2s ease;
}

.book-cover:hover {
  transform: scale(1.05);
}

img {
  height: 115px;
}
</style>
