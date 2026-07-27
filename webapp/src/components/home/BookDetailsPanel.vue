<template>
  <q-dialog
    v-model="showPanel"
    :full-height="!$q.screen.lt.sm"
    :maximized="false"
    :position="$q.screen.lt.sm ? 'bottom' : 'right'"
    :transition-hide="$q.screen.lt.sm ? 'slide-down' : 'slide-right'"
    :transition-show="$q.screen.lt.sm ? 'slide-up' : 'slide-left'"
  >
    <q-card class="details-panel column no-wrap">
      <!-- Drag handle (mobile only) -->
      <div v-if="$q.screen.lt.sm" class="drag-handle-wrap">
        <div class="drag-handle"></div>
      </div>

      <!-- Header: cover, title, author, rating, primary actions -->
      <q-card-section class="panel-head">
        <div class="row no-wrap items-start q-gutter-md">
          <div class="cover-wrap" :class="{ 'cursor-pointer': isOwnShelf && isBookInLibrary }" @click="onCoverClick">
            <img v-if="book?.thumbnail" :alt="`Cover of ${book?.title}`" class="cover" :src="book.thumbnail" />
            <BookCoverPlaceholder v-else :title="book?.title || ''" />
            <div v-if="isOwnShelf && isBookInLibrary" class="cover-overlay column flex-center">
              <q-icon color="white" name="swap_horiz" size="sm" />
              <div class="text-white text-caption">{{ $t('change-cover') }}</div>
            </div>
          </div>

          <div class="col column no-wrap">
            <div class="text-h5 ellipsis-2-lines">{{ book?.title }}</div>
            <div v-if="book?.subtitle" class="text-body2 text-grey-7 ellipsis">{{ book.subtitle }}</div>
            <div class="text-subtitle2 text-grey-8 q-mt-xs">{{ book?.authors }}</div>

            <div v-if="reviews.length" class="row items-center q-gutter-xs q-mt-xs">
              <q-rating color="amber" :model-value="averageRating" readonly size="xs" />
              <span class="text-caption text-grey-7">{{ averageRating.toFixed(1) }} · {{ $t('reviews-count', { count: reviews.length }) }}</span>
            </div>

            <!-- Out of the library: adding is the primary action, Amazon is secondary.
                 In the library: buying on Amazon is the primary action, removing is demoted to an icon. -->
            <div class="row no-wrap q-gutter-sm q-mt-auto">
              <template v-if="!isBookInLibrary">
                <q-btn
                  class="col"
                  color="primary"
                  data-testid="add-to-library-btn"
                  :disable="libraryLoading"
                  :label="$t('add-to-library')"
                  :loading="libraryLoading"
                  no-caps
                  rounded
                  unelevated
                  @click="$emit('add')"
                />
                <q-btn
                  v-if="amazonLink"
                  color="warning"
                  data-testid="amazon-btn"
                  :href="amazonLink"
                  icon="shopping_cart"
                  outline
                  round
                  target="_blank"
                  type="a"
                >
                  <q-tooltip>{{ $t('buy-on-amazon') }}</q-tooltip>
                </q-btn>
              </template>
              <template v-else>
                <q-btn
                  v-if="amazonLink"
                  class="col"
                  color="warning"
                  data-testid="amazon-btn"
                  :href="amazonLink"
                  icon="shopping_cart"
                  :label="$t('buy-on-amazon')"
                  no-caps
                  rounded
                  target="_blank"
                  text-color="dark"
                  type="a"
                  unelevated
                />
                <q-btn
                  v-if="amazonLink"
                  color="negative"
                  data-testid="remove-from-library-btn"
                  :disable="libraryLoading"
                  icon="bookmark_remove"
                  :loading="libraryLoading"
                  outline
                  round
                  @click="$emit('remove')"
                >
                  <q-tooltip>{{ $t('remove-from-library') }}</q-tooltip>
                </q-btn>
                <q-btn
                  v-else
                  class="col"
                  color="negative"
                  data-testid="remove-from-library-btn"
                  :disable="libraryLoading"
                  :label="$t('remove-from-library')"
                  :loading="libraryLoading"
                  no-caps
                  outline
                  rounded
                  @click="$emit('remove')"
                />
              </template>
            </div>
          </div>
        </div>

        <q-btn class="close-btn" data-testid="close-dialog-btn" dense flat icon="close" round @click="showPanel = false" />
      </q-card-section>

      <q-tabs
        v-model="tab"
        active-color="primary"
        align="left"
        class="panel-tabs text-grey-7"
        dense
        indicator-color="primary"
        narrow-indicator
        no-caps
      >
        <q-tab data-testid="tab-about" :label="$t('about')" name="about" />
        <q-tab v-if="isBookInLibrary || book?.pivot" data-testid="tab-reading" :label="$t('my-reading')" name="reading" />
        <q-tab data-testid="tab-reviews" :label="`${$t('reviews')} ${reviews.length || ''}`" name="reviews" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated class="col scroll">
        <!-- ABOUT -->
        <q-tab-panel name="about">
          <div v-if="book?.formatted_description || book?.description" class="text-body2 text-grey-8">
            <FormattedDescription
              v-if="book.formatted_description"
              :formatted-description="book.formatted_description"
              :show-full-description="showFullDescription"
            />
            <span v-else-if="!showFullDescription && (book.description?.length || 0) > 350">{{ book.description?.substring(0, 350) }}...</span>
            <span v-else>{{ book.description }}</span>
            <q-btn
              v-if="hasLongDescription"
              class="q-ml-xs"
              color="primary"
              dense
              flat
              :label="showFullDescription ? $t('see-less') : $t('see-more')"
              no-caps
              size="sm"
              @click="showFullDescription = !showFullDescription"
            />
          </div>

          <div v-if="hasInformation" class="info-grid q-mt-md">
            <div v-if="book?.page_count">
              <div class="info-label">{{ $t('pages') }}</div>
              <div class="text-body2">{{ book.page_count }}</div>
            </div>
            <div v-if="book?.publisher">
              <div class="info-label">{{ $t('publisher') }}</div>
              <div class="text-body2">{{ book.publisher }}</div>
            </div>
            <div v-if="displayLanguage">
              <div class="info-label">{{ $t('language') }}</div>
              <div class="text-body2">{{ displayLanguage }}</div>
            </div>
            <div v-if="book?.isbn">
              <div class="info-label">{{ $t('isbn') }}</div>
              <div class="text-body2">{{ book.isbn }}</div>
            </div>
          </div>

          <div class="q-mt-md">
            <slot name="tags" />
          </div>
        </q-tab-panel>

        <!-- MY READING -->
        <q-tab-panel name="reading">
          <div v-if="isOwnShelf" class="column q-gutter-md">
            <q-select
              v-model="form.reading_status"
              data-testid="reading-status-select"
              dense
              emit-value
              :label="$t('reading-status')"
              map-options
              :options="readingStatusOptions"
              outlined
              @update:model-value="emitPivot({ reading_status: form.reading_status })"
            />
            <q-input
              v-model="form.read_at"
              data-testid="read-date-input"
              dense
              :label="$t('read-date')"
              outlined
              type="date"
              @blur="emitPivot({ read_at: form.read_at })"
            />
            <div class="row items-center">
              <q-checkbox
                v-model="form.is_private"
                data-testid="private-book-checkbox"
                :label="$t('private-book')"
                @update:model-value="(value) => emitPivot({ is_private: value })"
              />
              <q-icon class="q-ml-xs cursor-pointer" name="help_outline" size="sm">
                <q-tooltip>{{ $t('private-book-tooltip') }}</q-tooltip>
              </q-icon>
            </div>
          </div>

          <div v-else class="column q-gutter-sm">
            <div>
              <div class="info-label">{{ $t('reading-status') }}</div>
              <div class="text-body2">{{ readingStatusLabel }}</div>
            </div>
            <div v-if="book?.pivot?.read_at">
              <div class="info-label">{{ $t('read-date') }}</div>
              <div class="text-body2">{{ new Date(book.pivot.read_at).toLocaleDateString() }}</div>
            </div>
          </div>
        </q-tab-panel>

        <!-- REVIEWS -->
        <q-tab-panel name="reviews">
          <slot name="reviews" />
        </q-tab-panel>
      </q-tab-panels>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import BookCoverPlaceholder from '@/components/common/BookCoverPlaceholder.vue'
import FormattedDescription from '@/components/common/FormattedDescription.vue'
import type { Book, ReadingStatus, Review } from '@/models'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(
  defineProps<{
    amazonLink?: string
    book?: Book
    isBookInLibrary?: boolean
    isOwnShelf?: boolean
    libraryLoading?: boolean
    reviews?: Review[]
  }>(),
  { reviews: () => [] }
)

const emit = defineEmits<{
  add: []
  remove: []
  'replace-cover': []
  'update-pivot': [updates: { is_private?: boolean; read_at?: string; reading_status?: ReadingStatus }]
}>()

const showPanel = defineModel<boolean>({ default: false })

const { t } = useI18n()

const form = reactive({
  is_private: false,
  read_at: '',
  reading_status: 'read' as ReadingStatus
})
const showFullDescription = ref(false)
const tab = ref('about')

const averageRating = computed(() => {
  if (!props.reviews.length) return 0
  return props.reviews.reduce((sum, review) => sum + review.rating, 0) / props.reviews.length
})

const displayLanguage = computed(() => {
  const language = props.book?.language
  if (!language) return null
  const key = `language-${language.toLowerCase().replace('-', '_')}`
  const translated = t(key)
  return translated !== key ? translated : language
})

const hasInformation = computed(() => !!(props.book?.isbn || props.book?.page_count || props.book?.publisher || displayLanguage.value))

const hasLongDescription = computed(() => {
  const book = props.book
  if (!book) return false
  if (book.formatted_description) {
    return (
      book.formatted_description.reduce((total, block) => {
        if (block.type === 'paragraph' && block.text) return total + block.text.length
        if (block.type === 'list' && block.items) return total + block.items.join(' ').length
        return total
      }, 0) > 350
    )
  }
  return (book.description?.length || 0) > 350
})

const readingStatusOptions = computed(() => [
  { label: t('want-to-read'), value: 'want_to_read' },
  { label: t('on-hold'), value: 'on_hold' },
  { label: t('reading'), value: 'reading' },
  { label: t('read'), value: 'read' },
  { label: t('re-reading'), value: 're_reading' },
  { label: t('abandoned'), value: 'abandoned' }
])

const readingStatusLabel = computed(() => {
  const status = props.book?.pivot?.reading_status
  return readingStatusOptions.value.find((option) => option.value === status)?.label || '-'
})

// Watch the pivot VALUES, not the book reference: Pinia's $patch deep-merges into
// the same object, so the reference never changes when the pivot arrives async.
watch(
  () => [props.book?.id, props.book?.pivot?.read_at, props.book?.pivot?.is_private, props.book?.pivot?.reading_status],
  () => {
    const pivot = props.book?.pivot
    form.is_private = Boolean(pivot?.is_private)
    form.read_at = pivot?.read_at ? new Date(pivot.read_at).toISOString().split('T')[0] || '' : ''
    form.reading_status = pivot?.reading_status || 'read'
  },
  { immediate: true }
)

watch(showPanel, (value) => {
  if (!value) {
    showFullDescription.value = false
    tab.value = 'about'
  }
})

function emitPivot(updates: { is_private?: boolean; read_at?: string; reading_status?: ReadingStatus }) {
  emit('update-pivot', updates)
}

function onCoverClick() {
  if (props.isOwnShelf && props.isBookInLibrary) emit('replace-cover')
}
</script>

<style scoped lang="sass">
.details-panel
  border-radius: 0
  height: 100vh
  max-height: 100vh
  max-width: 480px
  width: 480px
  @media screen and (max-width: $breakpoint-xs-max)
    border-radius: 20px 20px 0 0
    height: auto
    max-height: 88vh
    max-width: 100vw
    width: 100vw

.drag-handle-wrap
  display: flex
  justify-content: center
  padding: 0.5rem 0 0.125rem

.drag-handle
  background: rgba(0, 0, 0, 0.18)
  border-radius: 999px
  height: 4px
  width: 40px

.panel-head
  background: linear-gradient(180deg, #f6f8f9, #fff)
  border-bottom: 1px solid rgba(0, 0, 0, 0.08)
  position: relative

.close-btn
  position: absolute
  right: 0.75rem
  top: 0.75rem

.cover-wrap
  flex: 0 0 auto
  position: relative
  width: 96px

  .cover
    border-radius: 4px
    box-shadow: 0 8px 18px -6px rgba(0, 0, 0, 0.5)
    display: block
    width: 100%

  .cover-overlay
    background: rgba(0, 0, 0, 0.45)
    border-radius: 4px
    inset: 0
    opacity: 0
    position: absolute
    transition: opacity 0.2s

  &:hover .cover-overlay
    opacity: 1

.panel-tabs
  padding: 0 0.75rem

.info-grid
  background: #f7f9fa
  border-radius: 10px
  display: grid
  gap: 0.875rem 1.25rem
  grid-template-columns: 1fr 1fr
  padding: 0.875rem 1rem

.info-label
  color: rgba(0, 0, 0, 0.45)
  font-size: 0.625rem
  font-weight: 500
  letter-spacing: 0.6px
  text-transform: uppercase
</style>

<style lang="sass">
// Unscoped: the dialog container renders outside this component's root.
// QDialog's minimized container keeps a 24px vertical padding even with
// `full-height`, which would stop the drawer from touching the screen edges.
.q-dialog__inner--right.q-dialog__inner--fullheight
  padding-bottom: 0
  padding-top: 0
</style>
