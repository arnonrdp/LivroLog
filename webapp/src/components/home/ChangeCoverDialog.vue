<template>
  <q-dialog v-model="showDialog">
    <q-card style="max-width: 700px; width: 100%">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ $t('change-cover') }}</div>
        <q-space />
        <q-btn v-close-popup dense flat icon="close" round />
      </q-card-section>

      <q-separator />

      <q-card-section>
        <div class="text-body2 q-mb-md text-grey-8">
          {{ $t('change-cover-description') }}
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center q-py-lg">
          <q-spinner size="48px" />
          <div class="q-mt-md text-grey-7">{{ $t('loading-editions') }}</div>
        </div>

        <!-- Editions Grid -->
        <div v-else-if="editions.length > 0" class="editions-grid q-mt-md">
          <q-card
            v-for="edition in editions"
            :key="edition.id"
            bordered
            class="edition-card cursor-pointer"
            :class="{ 'current-edition': edition.id === currentBook.id }"
            flat
            @click="selectEdition(edition)"
          >
            <q-card-section class="text-center q-pa-sm">
              <!-- Thumbnail -->
              <div class="edition-thumbnail-wrapper">
                <img v-if="edition.thumbnail" :alt="edition.title" class="edition-thumbnail" :src="edition.thumbnail" />
                <q-icon v-else color="grey-5" name="book" size="80px" />
              </div>

              <!-- Format Badge -->
              <q-badge v-if="getFormatLabel(edition)" class="q-mt-sm" color="primary" :label="getFormatLabel(edition)" />

              <!-- ISBN -->
              <div v-if="edition.isbn" class="text-caption text-grey-7 q-mt-xs">ISBN: {{ edition.isbn }}</div>

              <!-- Current Edition Indicator -->
              <q-badge v-if="edition.id === currentBook.id" class="q-mt-xs" color="positive" :label="$t('current')" />
            </q-card-section>
          </q-card>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center q-py-lg text-grey-6">
          <q-icon class="q-mb-sm" name="book" size="3em" />
          <div class="text-body2">{{ $t('only-one-edition-available') }}</div>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>

  <!-- Confirmation Dialog -->
  <q-dialog v-model="showConfirmDialog">
    <q-card style="min-width: 350px">
      <q-card-section>
        <div class="text-h6">{{ $t('confirm-replace') }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <div class="text-body2">
          {{ $t('confirm-replace-message') }}
        </div>
        <div class="q-mt-md">
          <strong>{{ $t('from') }}:</strong>
          {{ currentBook?.title }}
        </div>
        <div class="q-mt-xs">
          <strong>{{ $t('to') }}:</strong>
          {{ selectedEdition?.title }}
        </div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup color="grey-6" flat :label="$t('cancel')" />
        <q-btn color="primary" flat :label="$t('confirm')" :loading="replacing" @click="confirmReplace" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import type { Book } from '@/models'
import { useBookStore, useUserBookStore } from '@/stores'
import { useQuasar } from 'quasar'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  currentBook: Book
}>()

const emit = defineEmits<{
  replaced: [book: Book]
}>()

const { t } = useI18n()
const $q = useQuasar()
const bookStore = useBookStore()
const userBookStore = useUserBookStore()

const showDialog = defineModel<boolean>({ default: false })

const loading = ref(false)
const editions = ref<Book[]>([])
const showConfirmDialog = ref(false)
const selectedEdition = ref<Book | null>(null)
const replacing = ref(false)

watch(showDialog, (newValue) => {
  if (newValue) {
    fetchEditions()
  } else {
    // Reset state when closing
    editions.value = []
    selectedEdition.value = null
  }
})

function fetchEditions() {
  if (!props.currentBook.id) {
    editions.value = []
    loading.value = false
    return
  }

  loading.value = true

  bookStore
    .getBookEditions(props.currentBook.id)
    .then((response) => {
      if (response.success && response.editions) {
        editions.value = response.editions
      } else {
        editions.value = []
      }
    })
    .catch((error) => {
      console.error('[ChangeCoverDialog] Error fetching editions:', error)
      $q.notify({
        message: t('error-occurred'),
        type: 'negative'
      })
      editions.value = []
    })
    .finally(() => {
      loading.value = false
    })
}

function selectEdition(edition: Book) {
  if (edition.id === props.currentBook.id) {
    return // Can't replace with same book
  }
  selectedEdition.value = edition
  showConfirmDialog.value = true
}

function confirmReplace() {
  if (!selectedEdition.value) return

  replacing.value = true

  userBookStore
    .replaceUserBook(props.currentBook.id, selectedEdition.value)
    .then((newBook) => {
      $q.notify({
        message: t('book-replaced-successfully'),
        type: 'positive'
      })
      emit('replaced', newBook)
      showDialog.value = false
      showConfirmDialog.value = false
    })
    .catch((error) => {
      console.error(error)
      // Error notification already handled in store
    })
    .finally(() => {
      replacing.value = false
    })
}

function getFormatLabel(book: Book): string | undefined {
  // Extract format from categories
  if (book.categories && Array.isArray(book.categories)) {
    const formatCategories = ['Kindle', 'Hardcover', 'Paperback', 'Audiobook', 'eBook']
    for (const category of book.categories) {
      for (const format of formatCategories) {
        if (category.toLowerCase().includes(format.toLowerCase())) {
          return format
        }
      }
    }
  }
  return undefined
}
</script>

<style scoped>
.editions-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.edition-card {
  transition: all 0.2s ease;
}

.edition-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.edition-card.current-edition {
  border-color: var(--q-positive);
  border-width: 2px;
}

.edition-thumbnail-wrapper {
  height: 150px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 8px;
}

.edition-thumbnail {
  max-width: 100%;
  max-height: 150px;
  object-fit: contain;
}

@media (max-width: 600px) {
  .editions-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }

  .edition-thumbnail-wrapper {
    height: 120px;
  }

  .edition-thumbnail {
    max-height: 120px;
  }
}
</style>
