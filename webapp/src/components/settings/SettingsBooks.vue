<template>
  <p>{{ $t('settings.books-description') }}</p>
  <p v-if="books?.length == 0">
    {{ $t('settings.bookshelf-empty') }}
    <router-link to="/add">{{ $t('settings.bookshelf-add-few') }}</router-link>
  </p>
  <table v-else class="q-mx-auto" :summary="t('settings.book-read-dates')">
    <thead>
      <tr>
        <th>{{ $t('settings.column-title') }}</th>
        <th>{{ $t('settings.column-readIn') }}</th>
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
    <q-btn color="primary" icon="save" :label="$t('settings.save')" :loading="bookStore.isLoading" @click="updateReadDates(books)" />
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

document.title = `LivroLog | ${t('settings.books')}`

onMounted(() => {
  books.value = bookStore.books
    .map((book) => ({
      ...book,
      readIn: book.readIn || book.pivot?.read_at || ''
    }))
    .reverse()
})

async function updateReadDates(updatedBooks: Book[]) {
  const updatedFields = []
  for (const book of updatedBooks) {
    if (book.readIn) {
      updatedFields.push({ id: book.id, readIn: String(book.readIn) })
    }
  }
  await bookStore
    .patchBooksReadDates(updatedFields)
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.read-dates-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.read-dates-updated-error') }))
}
</script>

<style scoped>
.input-date,
.input-date > label {
  min-width: 70px;
  padding-bottom: 0;
}
</style>
