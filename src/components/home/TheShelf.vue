<template>
  <div class="flex items-center">
    <h1 class="text-h5 text-primary text-left q-my-none">{{ $t('book.bookcase', [shelfName]) }}</h1>
    <q-space />
    <q-input borderless dense debounce="300" v-model="filter" :placeholder="$t('book.search')">
      <template v-slot:append>
        <q-icon name="search" />
      </template>
    </q-input>

    <ShelfDialog @sort="sort" />
  </div>
  <section class="flex justify-around">
    <figure v-for="book in books" v-show="onFilter(book.title)" :key="book.id">
      <q-btn
        v-if="selfUser"
        round
        color="negative"
        icon="close"
        size="sm"
        :title="$t('book.remove')"
        @click.once="$emit('emitRemoveID', book.id)"
      />
      <q-btn v-else round color="primary" icon="add" size="sm" :title="$t('book.add')" @click.once="$emit('emitAddID', book)" />
      <img v-if="book.thumbnail" :src="book.thumbnail" :alt="$t('book.cover-image-alt', [book.title])" />
      <img v-else src="@/assets/no_cover.jpg" alt="{{ $t('book.cover-image-alt', [book.title]) }}" />
      <!-- TODO: Manter tooltip ativa no mobile ao clicar na imagem do livro -->
      <q-tooltip anchor="bottom middle" self="center middle" class="bg-black">{{ book.title }}</q-tooltip>
    </figure>
  </section>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import type { Book, User } from '@/models'
import { useUserStore } from '@/store'
import { ref } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps<{
  books: User['books']
  shelfName: User['shelfName']
}>()

defineEmits(['emitRemoveID', 'emitAddID'])

const userStore = useUserStore()
const route = useRoute()

const ascDesc = ref('asc')
const filter = ref('')
const selfUser = ref(!route.params.username || route.params.username === userStore.getUser.username)
const sortKey = ref<string | number>('')

function onFilter(title: Book['title']) {
  return title.toLowerCase().includes(filter.value.toLowerCase())
}

function sort(label: string | number) {
  sortKey.value = label
  ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'

  const multiplier = ascDesc.value === 'asc' ? 1 : -1

  if (!props.books) {
    return
  }
  // eslint-disable-next-line vue/no-mutating-props, @typescript-eslint/no-explicit-any
  return props.books.sort((a: any, b: any) => {
    if (a[label] > b[label]) return 1 * multiplier
    if (a[label] < b[label]) return -1 * multiplier
    return 0
  })
}
</script>

<style scoped>
h1 {
  letter-spacing: 1px;
}

label {
  width: 100px;
}

section {
  background-image: url('@/assets/shelfleft.jpg'), url('@/assets/shelfright.jpg'), url('@/assets/shelfcenter.jpg');
  background-repeat: repeat-y, repeat-y, repeat;
  background-position: top left, top right, 240px 0;
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
  right: -1rem;
  top: 1rem;
  visibility: hidden;
  z-index: 1;
}

figure button:hover,
figure:hover button {
  opacity: 1;
  transition: 0.5s;
  visibility: visible;
}

img {
  height: 115px;
}
</style>
