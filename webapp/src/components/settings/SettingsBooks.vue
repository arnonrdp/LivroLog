<template>
  <p>{{ $t('books-description') }}</p>
  <p v-if="books?.length == 0">
    {{ $t('bookshelf-empty') }}
    <router-link to="/add">{{ $t('bookshelf-add-few') }}</router-link>
  </p>
  <table v-else class="q-mx-auto">
    <thead>
      <tr>
        <th>{{ $t('column-title') }}</th>
        <th>{{ $t('column-readIn') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="book in books" :key="book.id">
        <td class="text-left">{{ book.title }}</td>
        <td class="input-date">
          <q-input v-model="book.readIn" dense mask="####-##-##" :rules="[(val) => /^\d{4}-\d{2}-\d{2}$/.test(val) || 'YYYY-MM-DD']">
            <template v-slot:prepend>
              <q-icon class="cursor-pointer" name="event">
                <q-popup-proxy ref="qDateProxy" cover transition-hide="scale" transition-show="scale">
                  <q-date v-model="book.readIn" mask="YYYY-MM-DD" minimal />
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </td>
      </tr>
    </tbody>
  </table>
  <br />
  <div class="text-center">
    <q-btn color="primary" icon="save" :label="$t('save')" :loading="bookStore.isLoading" @click="updateReadDates(books)" />
  </div>
</template>

<script setup lang="ts">
import type { Book } from '@/models'
import { useBookStore } from '@/stores'
import { useQuasar } from 'quasar'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const bookStore = useBookStore()

const books = ref([] as Book[])

document.title = `LivroLog | ${t('books')}`

onMounted(() => {
  // Fetch all books without pagination for settings page
  bookStore.getBooks(true).then(() => {
    books.value = bookStore.books.map((book) => ({
      ...book,
      readIn: book.readIn || book.pivot?.read_at || ''
    }))
  })
})

async function updateReadDates(updatedBooks: Book[]) {
  const promises = []
  for (const book of updatedBooks) {
    if (book.readIn) {
      promises.push(bookStore.updateBookReadDate(book.id, String(book.readIn)))
    }
  }

  Promise.all(promises)
    .then(() => $q.notify({ message: t('read-dates-updated'), type: 'positive' }))
    .catch(() => $q.notify({ message: t('error-occurred'), type: 'negative' }))
}
</script>

<style scoped>
.input-date,
.input-date > label {
  min-width: 70px;
  padding-bottom: 0;
}
</style>
