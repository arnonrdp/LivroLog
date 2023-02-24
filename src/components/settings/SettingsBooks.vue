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
          <q-input dense v-model="book.readIn" mask="####-##-##" :rules="[(val) => /^\d{4}-\d{2}-\d{2}$/.test(val) || 'YYYY-MM-DD']">
            <template v-slot:prepend>
              <q-icon name="event" class="cursor-pointer">
                <q-popup-proxy ref="qDateProxy" cover transition-show="scale" transition-hide="scale">
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
    <q-btn color="primary" icon="save" :loading="saving" :label="$t('settings.save')" @click="updateReadDates(books)" />
  </div>
</template>

<script setup lang="ts">
import type { Book } from '@/models'
import { useBookStore } from '@/store'
import { useMeta, useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const bookStore = useBookStore()
const $q = useQuasar()
const { t } = useI18n()

const books = ref(bookStore.getBooks)
const saving = ref(false)

useMeta({
  title: `LivroLog | ${t('settings.books')}`,
  meta: {
    ogTitle: { name: 'og:title', content: `LivroLog | ${t('settings.books')}` },
    twitterTitle: { name: 'twitter:title', content: `LivroLog | ${t('settings.books')}` }
  }
})

async function updateReadDates(updatedBooks: Book[]) {
  saving.value = true
  const updatedFields: Pick<Book, 'id' | 'readIn'>[] = []
  for (const book of updatedBooks) {
    updatedFields.push({ id: book.id, readIn: book.readIn })
  }
  await bookStore
    .updateReadDates(updatedFields)
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.read-dates-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.read-dates-updated-error') }))
    .finally(() => (saving.value = false))
}
</script>

<style scoped>
.input-date,
.input-date > label {
  min-width: 70px;
  padding-bottom: 0;
}
</style>
