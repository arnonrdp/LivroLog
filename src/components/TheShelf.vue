<template>
  <div class="flex items-center">
    <h1 class="text-h5 text-primary text-left q-my-none">{{ $t('book.bookcase', [shelfName]) }}</h1>
    <q-space />
    <q-input borderless dense debounce="300" v-model="filter" :placeholder="$t('book.search')">
      <template v-slot:append>
        <q-icon name="search" />
      </template>
    </q-input>
    <!-- TODO: Habilitar botão quando os livros forem oriundos da Amazon
    <q-btn flat icon="download" @click="download" /> -->
    <q-btn-dropdown flat icon="filter_list" class="q-pr-none" size="md">
      <q-list class="non-selectable">
        <q-item clickable v-for="(label, name) in bookLabels" :key="label" @click="sort(books, name)">
          <q-item-section>{{ label }}</q-item-section>
          <q-item-section avatar>
            <q-icon v-if="name === sortKey" size="xs" :name="ascDesc === 'asc' ? 'arrow_downward' : 'arrow_upward'" />
          </q-item-section>
        </q-item>
      </q-list>
    </q-btn-dropdown>
  </div>
  <section ref="capture" class="flex justify-around">
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
      <img v-else src="../assets/no_cover.jpg" alt="{{ $t('book.cover-image-alt', [book.title]) }}" />
      <!-- TODO: Manter tooltip ativa no mobile ao clicar na imagem do livro -->
      <q-tooltip anchor="bottom middle" self="center middle" class="bg-black">{{ book.title }}</q-tooltip>
    </figure>
  </section>
</template>

<script setup lang="ts">
import type { Book, User } from '@/models'
import { useUserStore } from '@/store'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

defineProps<{
  books: User['books']
  shelfName: User['shelfName']
}>()

defineEmits(['emitRemoveID', 'emitAddID'])

const userStore = useUserStore()
const { t } = useI18n()
const route = useRoute()

const filter = ref('')
const ascDesc = ref('asc')
const sortKey = ref<string | number>('')
const selfUser = ref(!route.params.username || route.params.username === userStore.getUser.username)

const bookLabels = ref({
  authors: t('book.order-by-author'),
  addedIn: t('book.order-by-date'),
  readIn: t('book.order-by-read'),
  title: t('book.order-by-title')
})

function onFilter(title: Book['title']) {
  return title.toLowerCase().includes(filter.value.toLowerCase())
}

function sort(books: Book[] | undefined, label: string | number) {
  sortKey.value = label
  ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'

  const multiplier = ascDesc.value === 'asc' ? 1 : -1

  return books?.sort((a, b) => {
    if (a[label] > b[label]) {
      return 1 * multiplier
    }
    return a[label] < b[label] ? -1 : 0 * multiplier
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
