<template>
  <!-- Empty state when user has no books and is viewing their own shelf -->
  <EmptyShelfState v-if="!userIdentifier && (!books || books.length === 0)" :shelf-texture="shelfTexture" @import="handleImport" />

  <!-- Regular shelf display -->
  <section v-else class="flex justify-around" :style="shelfStyle">
    <figure
      v-for="book in books"
      v-show="onFilter(book.title)"
      :key="book.id"
      :class="{ dragging: draggedId === book.id, 'drop-target': dropTargetId === book.id, reorderable }"
      :data-book-id="book.id"
      data-testid="library-book"
      :draggable="reorderable && !coarsePointer"
      @contextmenu="touchDragging && $event.preventDefault()"
      @dragend="draggedId = dropTargetId = null"
      @dragenter.prevent="reorderable && draggedId && (dropTargetId = book.id)"
      @dragover.prevent
      @dragstart="onDragStart($event, book.id)"
      @drop.prevent="onDrop(book.id)"
      @touchcancel="endTouchDrag"
      @touchend="onTouchEnd"
      @touchmove="onTouchMove"
      @touchstart.passive="onTouchStart($event, book.id)"
    >
      <!-- Private book indicator removed - privacy info available in BookDialog -->

      <!-- Make the book image clickable only for authenticated users on other shelves -->
      <!-- A real href so crawlers (and cmd+click) can reach the book page; a plain click still
           opens the dialog, which is what a reader browsing a shelf expects. -->
      <a :class="['book-cover', { clickable: canOpenBookDialog }]" :href="`/books/${book.id}`" @click.exact.prevent="openBookDialog(book)">
        <img v-if="book.thumbnail" :alt="`Cover of ${book.title}`" :src="book.thumbnail" />
        <BookCoverPlaceholder v-else :title="book.title" />

        <!-- Tag dots - shown when sorted by tags -->
        <div v-if="showTagDots && getBookTags(book.id).length > 0" class="tag-dots">
          <div v-for="tag in getBookTags(book.id).slice(0, 3)" :key="tag.id" class="tag-dot" :style="{ backgroundColor: tag.color }">
            <q-tooltip>{{ tag.name }}</q-tooltip>
          </div>
        </div>
      </a>

      <q-tooltip anchor="bottom middle" class="bg-black" self="center middle">
        {{ book.title }}
      </q-tooltip>
    </figure>
  </section>

  <!-- Book Dialog -->
  <BookDialog v-model="showBookDialog" :book-id="selectedBookId" :user-identifier="props.userIdentifier" />

  <!-- GoodReads Import Dialog -->
  <GoodReadsImportDialog v-model="showImportDialog" @import-completed="onImportCompleted" />
</template>

<script setup lang="ts">
import BookCoverPlaceholder from '@/components/common/BookCoverPlaceholder.vue'
import { shelfTextureStyle, type ShelfTextureId } from '@/config/shelfTextures'
import type { Book, Tag, User } from '@/models'
import { useAuthStore, useTagStore } from '@/stores'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import BookDialog from './BookDialog.vue'
import EmptyShelfState from './EmptyShelfState.vue'
import GoodReadsImportDialog from './GoodReadsImportDialog.vue'

const props = defineProps<{
  shelfTexture?: ShelfTextureId
  books?: User['books']
  userIdentifier?: string // if provided, means viewing another user's shelf
  showTagDots?: boolean // Show tag color dots on book covers
  reorderable?: boolean // Owner can drag books to set a custom order
}>()

const emit = defineEmits<{
  'import-completed': []
  reorder: [fromId: string, toId: string]
}>()

const shelfStyle = computed(() => shelfTextureStyle(props.shelfTexture || 'wood'))

const authStore = useAuthStore()
const tagStore = useTagStore()
const router = useRouter()

const filter = ref('')
const showBookDialog = ref(false)
const showImportDialog = ref(false)
const selectedBookId = ref<string | undefined>()
const draggedId = ref<string | null>(null)
const dropTargetId = ref<string | null>(null)
const touchDragging = ref(false)
// Touch browsers don't start HTML5 drag from a finger, so phones get a long-press drag instead
const coarsePointer = window.matchMedia?.('(pointer: coarse)').matches ?? false
let touchTimer: ReturnType<typeof setTimeout> | null = null
let touchStart = { x: 0, y: 0 }

const canOpenBookDialog = computed(() => {
  // Books are always clickable - both on own shelf and public profiles
  // The BookDialog handles authentication-specific features appropriately
  return true
})

function onFilter(title: Book['title']) {
  return title.toLowerCase().includes(filter.value.toLowerCase())
}

function openBookDialog(book: Book) {
  if (!canOpenBookDialog.value) return

  // If viewing another user's shelf and not authenticated, navigate to book page
  if (props.userIdentifier && !authStore.isAuthenticated) {
    router.push({ name: 'book', params: { bookId: book.id } })
    return
  }

  // Otherwise, open the dialog
  selectedBookId.value = book.id
  showBookDialog.value = true
}

function onDragStart(event: DragEvent, bookId: string) {
  if (!props.reorderable) return
  draggedId.value = bookId
  // The cover is a link, so the browser would otherwise drag its URL around
  event.dataTransfer?.setData('text/plain', bookId)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function onDrop(targetId: string) {
  const fromId = draggedId.value
  draggedId.value = dropTargetId.value = null
  if (props.reorderable && fromId && fromId !== targetId) emit('reorder', fromId, targetId)
}

function onTouchStart(event: TouchEvent, bookId: string) {
  if (!props.reorderable || event.touches.length > 1) return
  const touch = event.touches[0]!
  touchStart = { x: touch.clientX, y: touch.clientY }
  touchTimer = setTimeout(() => {
    touchTimer = null
    touchDragging.value = true
    draggedId.value = bookId
    navigator.vibrate?.(20)
  }, 400)
}

function onTouchMove(event: TouchEvent) {
  const touch = event.touches[0]!
  if (touchTimer) {
    // Finger moved before the long press: it's a scroll, let it be
    if (Math.hypot(touch.clientX - touchStart.x, touch.clientY - touchStart.y) > 10) endTouchDrag()
    return
  }
  if (!touchDragging.value) return
  event.preventDefault()
  const target = document.elementFromPoint(touch.clientX, touch.clientY)?.closest('[data-book-id]')
  dropTargetId.value = target?.getAttribute('data-book-id') ?? null
}

function onTouchEnd(event: TouchEvent) {
  if (!touchDragging.value) return endTouchDrag()
  // Swallow the click so dropping doesn't also open the book dialog
  event.preventDefault()
  touchDragging.value = false
  if (dropTargetId.value) onDrop(dropTargetId.value)
  else endTouchDrag()
}

function endTouchDrag() {
  if (touchTimer) clearTimeout(touchTimer)
  touchTimer = null
  touchDragging.value = false
  draggedId.value = dropTargetId.value = null
}

function handleImport() {
  showImportDialog.value = true
}

function onImportCompleted() {
  emit('import-completed')
}

// Extended book type with tags
interface BookWithTags extends Book {
  tags?: Tag[]
}

function getBookTags(bookId: string): Tag[] {
  // First check if book has tags property
  const book = props.books?.find((b) => b.id === bookId) as BookWithTags | undefined
  if (book?.tags && book.tags.length > 0) {
    return book.tags
  }
  // Otherwise get from tag store
  return tagStore.getTagsForBook(bookId)
}
</script>

<style scoped lang="sass">
section
  border-radius: 6px
  // Finish on a complete texture row, without starting a partial next row.
  min-height: 292px
  padding: 0 3rem
  align-content: flex-start

section figure
  &[draggable="true"]
    cursor: grab

  // Long press is our drag gesture, so keep iOS/Android link previews and image drags out of it
  &.reorderable :is(a, img)
    -webkit-touch-callout: none
    -webkit-user-drag: none
    user-select: none

  &.dragging
    opacity: 0.4

  &.drop-target
    outline: 2px dashed rgba(255, 255, 255, 0.8)
    outline-offset: 2px
    border-radius: 4px

  align-items: flex-end
  display: flex
  height: 146px
  padding-bottom: var(--shelf-book-bottom, 7px)
  margin: 0 1.5rem
  position: relative

  & .private-indicator
    align-items: center
    background: rgba(0, 0, 0, 0.7)
    border-radius: 50%
    color: red
    display: flex
    justify-content: center
    padding: 0.25rem
    position: absolute
    right: -0.5rem
    top: 1rem
    z-index: 1

.book-cover
  display: block
  line-height: 0
  transform-origin: center bottom
  color: inherit
  text-decoration: none
  position: relative
  transition: transform 0.2s ease

  &.clickable
    cursor: pointer
    &:hover
      transform: scale(1.05)

  &:not(.clickable)
    cursor: default

img, :deep(.book-placeholder)
  display: block
  height: var(--shelf-book-height, 115px)
  width: auto

.tag-dots
  position: absolute
  top: 4px
  right: 4px
  display: flex
  gap: 2px
  flex-direction: row-reverse

.tag-dot
  width: 10px
  height: 10px
  border-radius: 50%
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3)
  cursor: help
</style>
