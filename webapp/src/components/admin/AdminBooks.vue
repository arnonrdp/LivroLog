<template>
  <q-table
    v-model:pagination="pagination"
    binary-state-sort
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
      <div class="row items-center full-width q-mb-md" style="gap: 16px">
        <q-input v-model="filter" clearable debounce="300" dense :placeholder="$t('admin.search-books')" style="width: 300px">
          <template v-slot:prepend>
            <q-icon name="search" />
          </template>
        </q-input>
        <q-space />
        <q-btn color="primary" icon="add" :label="$t('admin.add-book')" @click="openCreateDialog" />
      </div>
    </template>

    <template v-slot:body-cell-thumbnail="props">
      <q-td :props="props">
        <img
          v-if="props.row.thumbnail"
          alt="Cover"
          class="book-thumbnail cursor-pointer"
          :src="props.row.thumbnail"
          @click="expandImage(props.row.thumbnail)"
        />
        <q-icon v-else color="grey-5" name="image" size="24px" />
      </q-td>
    </template>

    <template v-slot:body-cell-amazon_asin="props">
      <q-td :props="props">
        <span v-if="props.row.amazon_asin" class="text-positive">{{ props.row.amazon_asin }}</span>
        <q-icon v-else color="grey-5" name="remove" size="16px" />
      </q-td>
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
        <q-btn
          :color="props.row.amazon_asin ? 'positive' : 'orange'"
          dense
          flat
          :icon="props.row.amazon_asin ? 'check_circle' : 'cloud_download'"
          :loading="isEnriching(props.row.id)"
          round
          size="sm"
          @click="enrichWithAmazon(props.row)"
        >
          <q-tooltip>{{ props.row.amazon_asin ? $t('admin.refresh-amazon') : $t('admin.enrich-amazon') }}</q-tooltip>
        </q-btn>
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

  <!-- Edit/Create Dialog -->
  <q-dialog v-model="editDialog" persistent>
    <q-card style="min-width: 500px; max-width: 600px">
      <q-card-section>
        <div class="text-h6">{{ isEditMode ? $t('admin.edit-book') : $t('admin.add-book') }}</div>
      </q-card-section>

      <!-- Mode selector (only for create mode) -->
      <q-tabs v-if="!isEditMode" v-model="createMode" active-color="primary" class="text-grey-7" dense indicator-color="primary" no-caps>
        <q-tab :label="$t('admin.create-from-amazon')" name="amazon" />
        <q-tab :label="$t('admin.create-manual')" name="manual" />
      </q-tabs>
      <q-separator v-if="!isEditMode" />

      <!-- Amazon URL mode -->
      <template v-if="!isEditMode && createMode === 'amazon'">
        <q-card-section class="q-pt-lg q-pb-md">
          <q-input
            v-model="createAmazonUrl"
            autofocus
            dense
            :error="!!createAmazonUrlError"
            :error-message="createAmazonUrlError"
            :hint="$t('admin.amazon-url-hint')"
            :label="$t('admin.amazon-url')"
            outlined
            :placeholder="$t('admin.amazon-url-placeholder')"
            @keyup.enter="createBookFromAmazon"
          >
            <template v-slot:prepend>
              <q-icon name="link" />
            </template>
          </q-input>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn v-close-popup flat :label="$t('cancel')" />
          <q-btn
            color="primary"
            :disable="!createAmazonUrl.trim()"
            flat
            :label="$t('admin.create-book')"
            :loading="isCreatingFromAmazon"
            @click="createBookFromAmazon"
          />
        </q-card-actions>
      </template>

      <!-- Manual mode / Edit mode -->
      <template v-else>
        <q-card-section v-if="editForm.thumbnail" class="q-pt-none text-center">
          <img alt="Cover" class="book-cover-preview" :src="editForm.thumbnail" />
        </q-card-section>

        <q-card-section class="q-pt-none" style="max-height: 60vh; overflow-y: auto">
          <q-input
            v-model="editForm.title"
            class="q-mb-sm"
            dense
            :label="$t('admin.book-title') + ' *'"
            :rules="[(v) => !!v || $t('admin.book-title-required')]"
          />
          <q-input v-model="editForm.authors" class="q-mb-sm" dense :label="$t('admin.book-authors')" />
          <q-input v-model="editForm.isbn" class="q-mb-sm" dense label="ISBN" />
          <q-input v-model="editForm.amazon_asin" class="q-mb-sm" dense label="Amazon ASIN" />
          <q-input v-model="editForm.google_id" class="q-mb-sm" dense :label="$t('admin.book-google-id')" />
          <q-input v-model="editForm.language" class="q-mb-sm" dense :label="$t('admin.book-language')" />
          <q-input v-model="editForm.publisher" class="q-mb-sm" dense :label="$t('admin.book-publisher')" />
          <q-input v-model="editForm.page_count" class="q-mb-sm" dense :label="$t('admin.book-pages')" type="number" />
          <q-input v-model="editForm.published_date" class="q-mb-sm" dense :label="$t('admin.book-published-date')" mask="####-##-##">
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
          <q-input v-model="editForm.thumbnail" class="q-mb-sm" dense :label="$t('admin.book-thumbnail')" />
          <div class="q-mb-sm">
            <div class="text-caption q-mb-xs text-grey-7">{{ $t('admin.book-description') }}</div>
            <q-editor
              v-model="editForm.description"
              :dense="$q.screen.lt.md"
              flat
              min-height="150px"
              :toolbar="[
                ['bold', 'italic', 'underline'],
                ['unordered', 'ordered'],
                ['undo', 'redo']
              ]"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn v-close-popup flat :label="$t('cancel')" />
          <q-btn color="primary" flat :label="$t('save')" :loading="isSaving" @click="saveBook" />
        </q-card-actions>
      </template>
    </q-card>
  </q-dialog>

  <!-- Delete Confirmation Dialog -->
  <q-dialog v-model="deleteDialog" persistent>
    <q-card>
      <q-card-section class="row items-center">
        <q-icon class="q-mr-sm" color="negative" name="warning" size="2em" />
        <span>{{ $t('admin.confirm-delete-book', { title: bookToDelete?.title }) }}</span>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup flat :label="$t('cancel')" />
        <q-btn color="negative" flat :label="$t('admin.delete')" :loading="isDeleting" @click="deleteBook" />
      </q-card-actions>
    </q-card>
  </q-dialog>

  <!-- Image Expand Dialog -->
  <q-dialog v-model="imageDialog">
    <img alt="Cover" class="expanded-cover" :src="expandedImage" @click="imageDialog = false" />
  </q-dialog>

  <!-- Amazon Enrichment Dialog -->
  <q-dialog v-model="amazonDialog" persistent>
    <q-card style="min-width: 450px">
      <q-card-section>
        <div class="text-h6">{{ $t('admin.enrich-amazon') }}</div>
        <div class="text-caption text-grey-7 q-mt-sm">
          {{ amazonBook?.title }}
        </div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input
          v-model="amazonUrl"
          autofocus
          dense
          :error="!!amazonUrlError"
          :error-message="amazonUrlError"
          :hint="$t('admin.amazon-url-hint')"
          :label="$t('admin.amazon-url')"
          outlined
          :placeholder="$t('admin.amazon-url-placeholder')"
          @keyup.enter="submitAmazonUrl"
        >
          <template v-slot:prepend>
            <q-icon name="link" />
          </template>
        </q-input>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup flat :label="$t('cancel')" @click="closeAmazonDialog" />
        <q-btn
          color="primary"
          :disable="!amazonUrl.trim()"
          flat
          :label="$t('admin.enrich')"
          :loading="isEnrichingFromUrl"
          @click="submitAmazonUrl"
        />
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
  thumbnail: string | null
  description: string | null
  users_count: number
  created_at: string
}

interface EditForm {
  id: string
  title: string
  authors: string
  isbn: string
  amazon_asin: string
  google_id: string
  language: string
  publisher: string
  page_count: number | null
  published_date: string
  thumbnail: string
  description: string
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
const imageDialog = ref(false)
const expandedImage = ref('')
const bookToDelete = ref<AdminBook | null>(null)
const isEditMode = ref(false)
const editForm = ref<EditForm>({
  id: '',
  title: '',
  authors: '',
  isbn: '',
  amazon_asin: '',
  google_id: '',
  language: '',
  publisher: '',
  page_count: null,
  published_date: '',
  thumbnail: '',
  description: ''
})

// Track which books are being enriched (for loading states)
const enrichingBooks = ref<Set<string>>(new Set())

// Amazon enrichment dialog state
const amazonDialog = ref(false)
const amazonBook = ref<AdminBook | null>(null)
const amazonUrl = ref('')
const amazonUrlError = ref('')
const isEnrichingFromUrl = ref(false)

// Create dialog mode: 'manual' or 'amazon'
const createMode = ref<'manual' | 'amazon'>('amazon')
const createAmazonUrl = ref('')
const createAmazonUrlError = ref('')
const isCreatingFromAmazon = ref(false)

const columns = computed<QTableColumn<AdminBook>[]>(() => [
  { name: 'thumbnail', label: t('admin.book-cover'), field: 'thumbnail', align: 'center', style: 'width: 60px' },
  { name: 'title', label: t('admin.book-title'), field: 'title', align: 'left', sortable: true },
  { name: 'authors', label: t('admin.book-authors'), field: 'authors', align: 'left', sortable: true },
  { name: 'isbn', label: 'ISBN', field: 'isbn', align: 'left', sortable: true },
  { name: 'amazon_asin', label: 'ASIN', field: 'amazon_asin', align: 'center', sortable: true },
  { name: 'language', label: t('admin.book-language'), field: 'language', align: 'center', sortable: true },
  { name: 'page_count', label: t('admin.book-pages'), field: 'page_count', align: 'center', sortable: true },
  { name: 'users_count', label: t('admin.book-users'), field: 'users_count', align: 'center', sortable: true },
  { name: 'created_at', label: t('admin.added-at'), field: 'created_at', align: 'left', sortable: true },
  { name: 'actions', label: '', field: () => null, align: 'center', style: 'width: 130px' }
])

function expandImage(url: string) {
  expandedImage.value = url
  imageDialog.value = true
}

function isEnriching(bookId: string): boolean {
  return enrichingBooks.value.has(bookId)
}

function enrichWithAmazon(book: AdminBook) {
  // First, try PA-API automatically
  enrichingBooks.value.add(book.id)

  api
    .post(`/admin/books/${book.id}/enrich-amazon`, {})
    .then((response) => {
      const data = response.data

      if (data.success) {
        // PA-API worked! Update the book in the local list
        const index = books.value.findIndex((b) => b.id === book.id)
        if (index !== -1 && books.value[index]) {
          Object.assign(books.value[index], {
            amazon_asin: data.book.amazon_asin,
            thumbnail: data.book.thumbnail,
            isbn: data.book.isbn,
            page_count: data.book.page_count,
            publisher: data.book.publisher
          })
        }

        Notify.create({
          message: t('admin.amazon-enriched', {
            fields: data.fields_filled.length,
            source: data.source
          }),
          type: 'positive',
          caption: data.fields_filled.join(', ')
        })
      } else if (data.needs_url) {
        // PA-API failed, open dialog for manual URL input
        amazonBook.value = book
        amazonUrl.value = ''
        amazonUrlError.value = ''
        amazonDialog.value = true
      } else {
        Notify.create({
          message: data.message || t('admin.amazon-enrich-failed'),
          type: 'warning'
        })
      }
    })
    .catch((error) => {
      // On error, open dialog as fallback
      amazonBook.value = book
      amazonUrl.value = ''
      amazonUrlError.value = ''
      amazonDialog.value = true
    })
    .finally(() => {
      enrichingBooks.value.delete(book.id)
    })
}

function closeAmazonDialog() {
  amazonDialog.value = false
  amazonBook.value = null
  amazonUrl.value = ''
  amazonUrlError.value = ''
}

function isValidAmazonUrl(url: string): boolean {
  try {
    const parsed = new URL(url)
    const host = parsed.hostname.toLowerCase()
    // Accept amazon domains and short URL domains
    const validDomains = ['amazon.com', 'amazon.com.br', 'amazon.co.uk', 'amazon.ca', 'amazon.de', 'amazon.fr', 'amazon.es', 'amazon.it', 'amazon.co.jp', 'a.co', 'amzn.to', 'amzn.com']
    return validDomains.some((domain) => host === domain || host === `www.${domain}` || host.endsWith(`.${domain}`))
  } catch {
    return false
  }
}

function submitAmazonUrl() {
  if (!amazonBook.value || !amazonUrl.value.trim()) return

  const url = amazonUrl.value.trim()

  // Validate Amazon URL (including short URLs)
  if (!isValidAmazonUrl(url)) {
    amazonUrlError.value = t('admin.amazon-url-invalid')
    return
  }

  amazonUrlError.value = ''
  isEnrichingFromUrl.value = true
  enrichingBooks.value.add(amazonBook.value.id)

  const bookId = amazonBook.value.id

  api
    .post(`/admin/books/${bookId}/enrich-amazon`, { amazon_url: url })
    .then((response) => {
      const data = response.data

      if (data.success) {
        // Update the book in the local list
        const index = books.value.findIndex((b) => b.id === bookId)
        if (index !== -1 && books.value[index]) {
          Object.assign(books.value[index], {
            amazon_asin: data.book.amazon_asin,
            thumbnail: data.book.thumbnail,
            isbn: data.book.isbn,
            page_count: data.book.page_count,
            publisher: data.book.publisher
          })
        }

        Notify.create({
          message: t('admin.amazon-enriched', {
            fields: data.fields_filled.length,
            source: data.source
          }),
          type: 'positive',
          caption: data.fields_filled.join(', ')
        })

        closeAmazonDialog()
      } else {
        amazonUrlError.value = data.message || t('admin.amazon-enrich-failed')
      }
    })
    .catch((error) => {
      // For 500 errors, show generic message (don't expose technical details)
      // For 4xx errors, show the backend message (which are user-friendly)
      const status = error.response?.status
      if (status >= 500) {
        amazonUrlError.value = t('admin.amazon-enrich-error')
      } else {
        amazonUrlError.value = error.response?.data?.message || t('admin.amazon-enrich-error')
      }
    })
    .finally(() => {
      isEnrichingFromUrl.value = false
      enrichingBooks.value.delete(bookId)
    })
}

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

function openCreateDialog() {
  isEditMode.value = false
  createMode.value = 'amazon'
  createAmazonUrl.value = ''
  createAmazonUrlError.value = ''
  editForm.value = {
    id: '',
    title: '',
    authors: '',
    isbn: '',
    amazon_asin: '',
    google_id: '',
    language: '',
    publisher: '',
    page_count: null,
    published_date: '',
    thumbnail: '',
    description: ''
  }
  editDialog.value = true
}

function openEditDialog(book: AdminBook) {
  isEditMode.value = true
  const publishedDate = book.published_date ? book.published_date.substring(0, 10) : ''
  editForm.value = {
    id: book.id,
    title: book.title || '',
    authors: book.authors || '',
    isbn: book.isbn || '',
    amazon_asin: book.amazon_asin || '',
    google_id: book.google_id || '',
    language: book.language || '',
    publisher: book.publisher || '',
    page_count: book.page_count,
    published_date: publishedDate,
    thumbnail: book.thumbnail || '',
    description: book.description || ''
  }
  editDialog.value = true
}

function createBookFromAmazon() {
  if (!createAmazonUrl.value.trim()) return

  const url = createAmazonUrl.value.trim()

  if (!isValidAmazonUrl(url)) {
    createAmazonUrlError.value = t('admin.amazon-url-invalid')
    return
  }

  createAmazonUrlError.value = ''
  isCreatingFromAmazon.value = true

  api
    .post('/admin/books/create-from-amazon', { amazon_url: url })
    .then((response) => {
      const data = response.data

      if (data.success) {
        Notify.create({
          message: t('admin.book-created'),
          type: 'positive',
          caption: data.book.title
        })
        editDialog.value = false
        fetchBooks(pagination.value.page, pagination.value.rowsPerPage, filter.value, pagination.value.sortBy, pagination.value.descending)
      } else {
        createAmazonUrlError.value = data.message || t('admin.error-creating')
      }
    })
    .catch((error) => {
      const status = error.response?.status
      if (status >= 500) {
        createAmazonUrlError.value = t('admin.amazon-enrich-error')
      } else {
        createAmazonUrlError.value = error.response?.data?.message || t('admin.error-creating')
      }
    })
    .finally(() => {
      isCreatingFromAmazon.value = false
    })
}

function saveBook() {
  if (!editForm.value.title.trim()) {
    Notify.create({ message: t('admin.book-title-required'), type: 'negative' })
    return
  }

  isSaving.value = true

  const bookData = {
    title: editForm.value.title,
    authors: editForm.value.authors || null,
    isbn: editForm.value.isbn || null,
    amazon_asin: editForm.value.amazon_asin || null,
    google_id: editForm.value.google_id || null,
    language: editForm.value.language || null,
    publisher: editForm.value.publisher || null,
    page_count: editForm.value.page_count,
    published_date: editForm.value.published_date || null,
    thumbnail: editForm.value.thumbnail || null,
    description: editForm.value.description || null
  }

  const request = isEditMode.value ? api.put(`/books/${editForm.value.id}`, bookData) : api.post('/books', bookData)

  request
    .then(() => {
      Notify.create({ message: t(isEditMode.value ? 'admin.book-updated' : 'admin.book-created'), type: 'positive' })
      editDialog.value = false
      fetchBooks(pagination.value.page, pagination.value.rowsPerPage, filter.value, pagination.value.sortBy, pagination.value.descending)
    })
    .catch((error) => {
      Notify.create({
        message: error.response?.data?.message || t(isEditMode.value ? 'admin.error-updating' : 'admin.error-creating'),
        type: 'negative'
      })
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

.book-thumbnail
  width: 32px
  height: 48px
  object-fit: cover
  border-radius: 2px
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2)
  transition: transform 0.2s
  &:hover
    transform: scale(1.1)

.expanded-cover
  max-width: 90vw
  max-height: 90vh
  object-fit: contain
  cursor: pointer
</style>
