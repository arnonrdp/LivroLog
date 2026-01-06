<template>
  <q-table
    v-model:pagination="pagination"
    :columns="columns"
    :dense="$q.screen.lt.md"
    :filter="filter"
    flat
    :loading="isLoading"
    row-key="id"
    :rows="books"
    :rows-per-page-options="[10, 25, 50]"
    @request="onRequest"
  >
    <template v-slot:top>
      <q-input v-model="filter" class="q-mb-md" clearable debounce="300" dense :placeholder="$t('admin.search-books')" style="width: 300px">
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
      </q-input>
    </template>

    <template v-slot:body-cell-title="props">
      <q-td :props="props">
        <router-link :to="`/books/${props.row.id}`">{{ props.row.title }}</router-link>
      </q-td>
    </template>

    <template v-slot:body-cell-created_at="props">
      <q-td :props="props">
        {{ formatDate(props.row.created_at) }}
      </q-td>
    </template>

    <template v-slot:body-cell-actions="props">
      <q-td :props="props">
        <q-btn dense flat icon="edit" round size="sm" @click="openEditDialog(props.row)">
          <q-tooltip>{{ $t('admin.edit') }}</q-tooltip>
        </q-btn>
        <q-btn color="negative" dense flat icon="delete" round size="sm" @click="confirmDelete(props.row)">
          <q-tooltip>{{ $t('admin.delete') }}</q-tooltip>
        </q-btn>
      </q-td>
    </template>

    <template v-slot:no-data>
      <div class="full-width text-center q-py-lg text-grey-6">
        {{ $t('admin.no-books-found') }}
      </div>
    </template>
  </q-table>

  <!-- Edit Dialog -->
  <q-dialog v-model="editDialog" persistent>
    <q-card style="min-width: 500px">
      <q-card-section>
        <div class="text-h6">{{ $t('admin.edit-book') }}</div>
      </q-card-section>

      <q-card-section v-if="editForm.thumbnail" class="q-pt-none text-center">
        <img :src="editForm.thumbnail" alt="Cover" class="book-cover-preview" />
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input v-model="editForm.title" dense :label="$t('admin.book-title')" class="q-mb-sm" />
        <q-input v-model="editForm.authors" dense :label="$t('admin.book-authors')" class="q-mb-sm" />
        <q-input v-model="editForm.isbn" dense label="ISBN" class="q-mb-sm" />
        <q-input v-model="editForm.amazon_asin" dense label="Amazon ASIN" class="q-mb-sm" />
        <q-input v-model="editForm.language" dense :label="$t('admin.book-language')" class="q-mb-sm" />
        <q-input v-model="editForm.publisher" dense :label="$t('admin.book-publisher')" class="q-mb-sm" />
        <q-input v-model="editForm.page_count" dense :label="$t('admin.book-pages')" type="number" class="q-mb-sm" />
        <q-input v-model="editForm.published_date" dense :label="$t('admin.book-published-date')" mask="####-##-##" class="q-mb-sm">
          <template v-slot:append>
            <q-icon class="cursor-pointer" name="event">
              <q-popup-proxy cover transition-hide="scale" transition-show="scale">
                <q-date v-model="editForm.published_date" mask="YYYY-MM-DD" minimal>
                  <div class="row items-center justify-end">
                    <q-btn v-close-popup color="primary" flat :label="$t('close')" />
                  </div>
                </q-date>
              </q-popup-proxy>
            </q-icon>
          </template>
        </q-input>
        <q-input v-model="editForm.thumbnail" dense :label="$t('admin.book-thumbnail')" class="q-mb-sm" />
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup flat :label="$t('cancel')" />
        <q-btn color="primary" flat :label="$t('save')" :loading="isSaving" @click="saveBook" />
      </q-card-actions>
    </q-card>
  </q-dialog>

  <!-- Delete Confirmation Dialog -->
  <q-dialog v-model="deleteDialog" persistent>
    <q-card>
      <q-card-section class="row items-center">
        <q-icon color="negative" name="warning" size="2em" class="q-mr-sm" />
        <span>{{ $t('admin.confirm-delete-book', { title: bookToDelete?.title }) }}</span>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup flat :label="$t('cancel')" />
        <q-btn color="negative" flat :label="$t('admin.delete')" :loading="isDeleting" @click="deleteBook" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import api from '@/utils/axios'
import type { QTableColumn, QTableProps } from 'quasar'
import { Notify, useQuasar } from 'quasar'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

interface AdminBook {
  id: string
  title: string
  authors: string | null
  isbn: string | null
  google_id: string | null
  amazon_asin: string | null
  language: string | null
  page_count: number | null
  publisher: string | null
  published_date: string | null
  thumbnail?: string | null
  users_count: number
  created_at: string
}

interface EditForm {
  id: string
  title: string
  authors: string
  isbn: string
  amazon_asin: string
  language: string
  publisher: string
  page_count: number | null
  published_date: string
  thumbnail: string
}

const $q = useQuasar()
const { t, locale } = useI18n()

const books = ref<AdminBook[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const filter = ref('')
const pagination = ref({
  page: 1,
  rowsPerPage: 25,
  rowsNumber: 0,
  sortBy: 'created_at',
  descending: true
})

const editDialog = ref(false)
const deleteDialog = ref(false)
const bookToDelete = ref<AdminBook | null>(null)
const editForm = ref<EditForm>({
  id: '',
  title: '',
  authors: '',
  isbn: '',
  amazon_asin: '',
  language: '',
  publisher: '',
  page_count: null,
  published_date: '',
  thumbnail: ''
})

const columns = computed<QTableColumn<AdminBook>[]>(() => [
  { name: 'title', label: t('admin.book-title'), field: 'title', align: 'left', sortable: true },
  { name: 'authors', label: t('admin.book-authors'), field: 'authors', align: 'left', sortable: true },
  { name: 'isbn', label: 'ISBN', field: 'isbn', align: 'left', sortable: true },
  { name: 'language', label: t('admin.book-language'), field: 'language', align: 'center', sortable: true },
  { name: 'page_count', label: t('admin.book-pages'), field: 'page_count', align: 'center', sortable: true },
  { name: 'users_count', label: t('admin.book-users'), field: 'users_count', align: 'center', sortable: true },
  { name: 'created_at', label: t('admin.added-at'), field: 'created_at', align: 'left', sortable: true },
  { name: 'actions', label: '', field: () => null, align: 'center', style: 'width: 100px' }
])

function formatDate(dateString: string): string {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString(locale.value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function fetchBooks(page: number, perPage: number, filterValue: string, sortBy: string, descending: boolean) {
  isLoading.value = true
  api
    .get('/admin/books', {
      params: {
        page,
        per_page: perPage,
        filter: filterValue || undefined,
        sort_by: sortBy,
        sort_desc: descending
      }
    })
    .then((response) => {
      books.value = response.data.data
      pagination.value.rowsNumber = response.data.meta.total
      pagination.value.page = response.data.meta.current_page
      pagination.value.rowsPerPage = response.data.meta.per_page
    })
    .catch((error) => {
      console.error('Failed to fetch books:', error)
    })
    .finally(() => {
      isLoading.value = false
    })
}

function onRequest(props: Parameters<NonNullable<QTableProps['onRequest']>>[0]) {
  const { page, rowsPerPage, sortBy, descending } = props.pagination
  pagination.value.sortBy = sortBy ?? 'created_at'
  pagination.value.descending = descending ?? true
  fetchBooks(page, rowsPerPage, filter.value, pagination.value.sortBy, pagination.value.descending)
}

function openEditDialog(book: AdminBook) {
  const publishedDate = book.published_date ? book.published_date.substring(0, 10) : ''
  editForm.value = {
    id: book.id,
    title: book.title || '',
    authors: book.authors || '',
    isbn: book.isbn || '',
    amazon_asin: book.amazon_asin || '',
    language: book.language || '',
    publisher: book.publisher || '',
    page_count: book.page_count,
    published_date: publishedDate,
    thumbnail: book.thumbnail || ''
  }
  editDialog.value = true
}

function saveBook() {
  isSaving.value = true
  api
    .put(`/books/${editForm.value.id}`, {
      title: editForm.value.title,
      authors: editForm.value.authors || null,
      isbn: editForm.value.isbn || null,
      amazon_asin: editForm.value.amazon_asin || null,
      language: editForm.value.language || null,
      publisher: editForm.value.publisher || null,
      page_count: editForm.value.page_count,
      published_date: editForm.value.published_date || null,
      thumbnail: editForm.value.thumbnail || null
    })
    .then(() => {
      Notify.create({ message: t('admin.book-updated'), type: 'positive' })
      editDialog.value = false
      fetchBooks(pagination.value.page, pagination.value.rowsPerPage, filter.value, pagination.value.sortBy, pagination.value.descending)
    })
    .catch((error) => {
      Notify.create({ message: error.response?.data?.message || t('admin.error-updating'), type: 'negative' })
    })
    .finally(() => {
      isSaving.value = false
    })
}

function confirmDelete(book: AdminBook) {
  bookToDelete.value = book
  deleteDialog.value = true
}

function deleteBook() {
  if (!bookToDelete.value) return

  isDeleting.value = true
  api
    .delete(`/books/${bookToDelete.value.id}`)
    .then(() => {
      Notify.create({ message: t('admin.book-deleted'), type: 'positive' })
      deleteDialog.value = false
      bookToDelete.value = null
      fetchBooks(pagination.value.page, pagination.value.rowsPerPage, filter.value, pagination.value.sortBy, pagination.value.descending)
    })
    .catch((error) => {
      Notify.create({ message: error.response?.data?.message || t('admin.error-deleting'), type: 'negative' })
    })
    .finally(() => {
      isDeleting.value = false
    })
}

onMounted(() => {
  fetchBooks(1, pagination.value.rowsPerPage, '', pagination.value.sortBy, pagination.value.descending)
})
</script>

<style scoped lang="sass">
a
  color: var(--q-primary)
  text-decoration: none
  &:hover
    text-decoration: underline

.book-cover-preview
  max-width: 120px
  max-height: 180px
  object-fit: contain
  border-radius: 4px
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15)
</style>
