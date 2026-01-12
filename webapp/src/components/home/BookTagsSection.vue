<template>
  <q-card-section v-if="isBookInLibrary && !userIdentifier" data-testid="book-tags-section">
    <div class="text-subtitle1 q-mb-md row items-center">
      <q-icon class="q-mr-sm" name="label" />
      {{ $t('tags.title') }}
    </div>

    <!-- Current tags as chips -->
    <div class="row q-gutter-sm q-mb-md">
      <q-chip
        v-for="tag in bookTags"
        :key="tag.id"
        clickable
        color="grey-2"
        data-testid="book-tag-chip"
        dense
        removable
        :style="{ borderLeft: `4px solid ${tag.color}` }"
        text-color="dark"
        @remove="removeTag(tag.id)"
      >
        {{ tag.name }}
      </q-chip>

      <!-- Add tag button -->
      <q-btn color="grey-5" data-testid="add-tag-btn" dense flat icon="add" round size="sm">
        <q-tooltip>{{ $t('tags.add') }}</q-tooltip>

        <q-menu v-model="showTagMenu" anchor="bottom left" data-testid="add-tag-menu" :offset="[0, 5]" self="top left">
          <q-card style="min-width: 250px; max-width: 300px">
            <q-card-section class="q-pb-none">
              <q-input
                v-model="searchQuery"
                autofocus
                data-testid="tag-search-input"
                dense
                :label="$t('tags.search-or-create')"
                outlined
                @keyup.enter="onEnterPressed"
              >
                <template #prepend>
                  <q-icon name="search" />
                </template>
              </q-input>
            </q-card-section>

            <q-card-section class="q-pt-sm">
              <!-- Suggestions for new users -->
              <div v-if="showSuggestions" class="q-mb-sm">
                <div class="text-caption text-grey-6 q-mb-xs">{{ $t('tags.suggestions') }}</div>
                <div class="row q-gutter-xs">
                  <q-chip
                    v-for="suggestion in filteredSuggestions"
                    :key="suggestion"
                    clickable
                    color="grey-3"
                    data-testid="tag-suggestion-chip"
                    dense
                    @click="createTagFromSuggestion(suggestion)"
                  >
                    + {{ suggestion }}
                  </q-chip>
                </div>
              </div>

              <!-- Existing tags -->
              <div v-if="filteredTags.length" class="q-list q-list--dense">
                <div
                  v-for="tag in filteredTags"
                  :key="tag.id"
                  class="tag-option-row"
                  :class="{ 'tag-option-row--active': isTagOnBook(tag.id) }"
                  data-testid="tag-option-item"
                  @click="handleTagClick(tag)"
                >
                  <div class="tag-color-dot" :style="{ backgroundColor: tag.color }"></div>
                  <span class="tag-option-name">{{ tag.name }}</span>
                  <q-icon v-if="isTagOnBook(tag.id)" class="tag-option-check" color="positive" name="check" size="xs" />
                </div>
              </div>

              <!-- Create new tag option -->
              <div
                v-if="searchQuery && !tagExists"
                class="q-item q-item--clickable q-link cursor-pointer q-focusable q-hoverable"
                data-testid="create-tag-option"
                @click="handleCreateTag"
              >
                <div class="q-item__section column q-item__section--avatar">
                  <q-icon color="primary" name="add" />
                </div>
                <div class="q-item__section column q-item__section--main justify-center">
                  {{ $t('tags.create-new', { name: searchQuery }) }}
                </div>
              </div>

              <div v-if="!filteredTags.length && !searchQuery && !showSuggestions" class="text-center text-grey-6 q-py-sm">
                {{ $t('tags.no-tags') }}
              </div>
            </q-card-section>
          </q-card>
        </q-menu>
      </q-btn>
    </div>
  </q-card-section>

  <!-- Create Tag Dialog -->
  <q-dialog v-model="showCreateDialog" data-testid="create-tag-dialog" persistent>
    <q-card style="min-width: 300px">
      <q-card-section>
        <div class="text-h6">{{ $t('tags.create-title') }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input v-model="newTagName" autofocus data-testid="new-tag-name-input" dense :label="$t('tags.name')" :maxlength="50" outlined />

        <div class="q-mt-md">
          <div class="text-caption text-grey-6 q-mb-sm">{{ $t('tags.choose-color') }}</div>
          <q-color
            v-model="selectedColor"
            class="color-picker-enhanced"
            data-testid="new-tag-color-picker"
            default-view="palette"
            no-footer
            no-header
            :palette="tagColors"
          />
        </div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup color="grey-6" data-testid="cancel-create-tag-btn" flat :label="$t('cancel')" @click="resetCreateDialog" />
        <q-btn
          color="primary"
          data-testid="submit-create-tag-btn"
          :disable="!newTagName.trim()"
          flat
          :label="$t('create')"
          :loading="isCreating"
          @click="createTag"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import type { Tag } from '@/models'
import { TAG_COLORS } from '@/models'
import { useTagStore } from '@/stores'
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps<{
  bookId: string
  isBookInLibrary: boolean
  userIdentifier?: string
}>()

const tagStore = useTagStore()

const searchQuery = ref('')
const showTagMenu = ref(false)
const showCreateDialog = ref(false)
const newTagName = ref('')
const selectedColor = ref<string>(TAG_COLORS[0])
const isCreating = ref(false)

const tagColors = computed(() => tagStore.meta.colors)

const bookTags = computed(() => tagStore.getTagsForBook(props.bookId))
const allTags = computed(() => tagStore.tags)

const filteredTags = computed(() => {
  const query = searchQuery.value.toLowerCase().trim()
  if (!query) return allTags.value
  return allTags.value.filter((tag) => tag.name.toLowerCase().includes(query))
})

const showSuggestions = computed(() => {
  return allTags.value.length === 0 && !searchQuery.value
})

const filteredSuggestions = computed(() => {
  const existingNames = allTags.value.map((t) => t.name.toLowerCase())
  return tagStore.meta.suggestions.filter((s) => !existingNames.includes(s.toLowerCase()))
})

const tagExists = computed(() => {
  const query = searchQuery.value.toLowerCase().trim()
  return allTags.value.some((tag) => tag.name.toLowerCase() === query)
})

onMounted(() => {
  // Load user's tags if not already loaded
  if (allTags.value.length === 0) {
    tagStore.getTags()
  }

  // Load book's tags
  if (props.bookId && props.isBookInLibrary) {
    tagStore.getBookTags(props.bookId)
  }
})

watch(
  () => props.bookId,
  (newBookId) => {
    if (newBookId && props.isBookInLibrary) {
      tagStore.getBookTags(newBookId)
    }
  }
)

// Watch isBookInLibrary to load book tags when book is added to library
watch(
  () => props.isBookInLibrary,
  (isInLibrary) => {
    if (isInLibrary && props.bookId) {
      // Also ensure user tags are loaded (in case of page navigation)
      if (allTags.value.length === 0) {
        tagStore.getTags()
      }
      tagStore.getBookTags(props.bookId)
    }
  }
)

function isTagOnBook(tagId: string): boolean {
  return bookTags.value.some((t) => t.id === tagId)
}

function toggleTag(tag: Tag): void {
  if (isTagOnBook(tag.id)) {
    removeTag(tag.id)
  } else {
    addTag(tag.id)
  }
}

function handleTagClick(tag: Tag): void {
  // Close the menu first, then toggle the tag
  showTagMenu.value = false
  toggleTag(tag)
}

function handleCreateTag(): void {
  // Close the menu first
  showTagMenu.value = false
  // Open create dialog
  showCreateDialog.value = true
}

function addTag(tagId: string): void {
  tagStore.postBookTagAdd(props.bookId, tagId)
}

function removeTag(tagId: string): void {
  tagStore.deleteBookTag(props.bookId, tagId)
}

function onEnterPressed(): void {
  if (searchQuery.value && !tagExists.value) {
    newTagName.value = searchQuery.value
    showCreateDialog.value = true
    showTagMenu.value = false
  }
}

function createTagFromSuggestion(suggestion: string): void {
  newTagName.value = suggestion
  showCreateDialog.value = true
  showTagMenu.value = false
}

function resetCreateDialog(): void {
  newTagName.value = ''
  selectedColor.value = TAG_COLORS[0]
}

function createTag(): void {
  if (!newTagName.value.trim()) return

  isCreating.value = true

  tagStore
    .postTag({
      name: newTagName.value.trim(),
      color: selectedColor.value
    })
    .then((tag) => {
      if (tag) {
        // Add the new tag to the book
        tagStore.postBookTagAdd(props.bookId, tag.id)
      }
      showCreateDialog.value = false
      resetCreateDialog()
      searchQuery.value = ''
    })
    .finally(() => {
      isCreating.value = false
    })
}
</script>

<style scoped lang="sass">
.tag-color-dot
  width: 12px
  height: 12px
  border-radius: 50%
  flex-shrink: 0

.tag-option-row
  display: flex
  align-items: center
  gap: 12px
  padding: 8px 12px
  cursor: pointer
  border-radius: 4px
  &:hover
    background-color: rgba(0, 0, 0, 0.05)
  &--active
    background-color: rgba(0, 0, 0, 0.03)

.tag-option-name
  flex: 1

.tag-option-check
  flex-shrink: 0
</style>

<style lang="sass">
// Unscoped to target QColor internal elements
.color-picker-enhanced
  .q-color-picker__cube--selected
    transform: scale(1.15)
    box-shadow: 0 0 0 2px white, 0 0 0 4px rgba(0, 0, 0, 0.3)
    position: relative
    &::after
      content: '✓'
      position: absolute
      color: white
      font-size: 14px
      font-weight: bold
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5)
</style>
