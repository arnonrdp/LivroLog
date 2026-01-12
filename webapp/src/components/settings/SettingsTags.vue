<template>
  <p>{{ $t('tags-description', 'Organize seus livros com etiquetas personalizadas.') }}</p>

  <div class="q-mb-md">
    <q-btn color="primary" data-testid="create-tag-btn" icon="add" :label="$t('tags.create-title')" no-caps @click="openCreateDialog" />
  </div>

  <q-table
    :columns="columns"
    data-testid="tags-table"
    :dense="$q.screen.lt.md"
    flat
    hide-bottom
    hide-pagination
    :loading="tagStore.isLoading"
    row-key="id"
    :rows="tagStore.tags"
    :rows-per-page-options="[0]"
  >
    <template v-slot:no-data>
      <div class="full-width text-center q-py-lg" data-testid="no-tags-message">
        {{ $t('tags.no-tags') }}
      </div>
    </template>

    <template v-slot:body="props">
      <q-tr data-testid="tag-row" :props="props">
        <q-td key="color" :props="props">
          <div class="tag-color-dot" :style="{ backgroundColor: props.row.color }"></div>
        </q-td>
        <q-td key="name" :props="props">
          <q-chip dense :style="{ borderLeft: `4px solid ${props.row.color}` }">
            {{ props.row.name }}
          </q-chip>
        </q-td>
        <q-td key="books_count" :props="props">
          <span data-testid="tag-books-count">{{ props.row.books_count || 0 }}</span>
        </q-td>
        <q-td key="actions" :props="props">
          <q-btn color="primary" data-testid="edit-tag-btn" dense flat icon="edit" round size="sm" @click="openEditDialog(props.row)">
            <q-tooltip>{{ $t('edit') }}</q-tooltip>
          </q-btn>
          <q-btn color="negative" data-testid="delete-tag-btn" dense flat icon="delete" round size="sm" @click="confirmDelete(props.row)">
            <q-tooltip>{{ $t('delete') }}</q-tooltip>
          </q-btn>
        </q-td>
      </q-tr>
    </template>
  </q-table>

  <!-- Create/Edit Tag Dialog -->
  <q-dialog v-model="showDialog" data-testid="tag-dialog" persistent>
    <q-card style="min-width: 350px">
      <q-card-section>
        <div class="text-h6">{{ editingTag ? $t('tags.edit-title') : $t('tags.create-title') }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input v-model="form.name" autofocus data-testid="tag-name-input" dense :label="$t('tags.name')" :maxlength="50" outlined />

        <div class="q-mt-md">
          <div class="text-caption text-grey-6 q-mb-sm">{{ $t('tags.choose-color') }}</div>
          <q-color
            v-model="form.color"
            class="color-picker-enhanced"
            data-testid="tag-color-picker"
            default-view="palette"
            no-footer
            no-header
            :palette="TAG_COLORS"
          />
        </div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn color="grey-6" data-testid="cancel-tag-btn" flat :label="$t('cancel')" @click="closeDialog" />
        <q-btn
          color="primary"
          data-testid="save-tag-btn"
          :disable="!form.name.trim()"
          flat
          :label="$t('save')"
          :loading="isSaving"
          @click="saveTag"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>

  <!-- Delete Confirmation Dialog -->
  <q-dialog v-model="showDeleteDialog" data-testid="confirm-delete-dialog" persistent>
    <q-card style="min-width: 350px">
      <q-card-section>
        <div class="text-h6">{{ $t('confirmDelete') }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        {{ $t('confirm-delete-tag', { name: tagToDelete?.name }) }}
        <div v-if="tagToDelete?.books_count" class="text-caption text-negative q-mt-sm">
          {{ $t('tag-delete-warning', { count: tagToDelete.books_count }) }}
        </div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn color="grey-6" data-testid="cancel-delete-btn" flat :label="$t('cancel')" @click="showDeleteDialog = false" />
        <q-btn color="negative" data-testid="confirm-delete-btn" flat :label="$t('delete')" :loading="isDeleting" @click="deleteTag" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import type { Tag } from '@/models'
import { TAG_COLORS } from '@/models'
import { useTagStore } from '@/stores'
import type { QTableColumn } from 'quasar'
import { useQuasar } from 'quasar'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()
const tagStore = useTagStore()

document.title = `LivroLog | ${t('tags.title')}`

const showDialog = ref(false)
const showDeleteDialog = ref(false)
const editingTag = ref<Tag | null>(null)
const tagToDelete = ref<Tag | null>(null)
const isSaving = ref(false)
const isDeleting = ref(false)

const form = reactive({
  name: '',
  color: TAG_COLORS[0] as string
})

const columns = computed<QTableColumn<Tag>[]>(() => [
  {
    name: 'color',
    label: '',
    field: 'color',
    align: 'center',
    style: 'width: 40px'
  },
  {
    name: 'name',
    label: t('tags.name'),
    field: 'name',
    align: 'left',
    sortable: true
  },
  {
    name: 'books_count',
    label: t('books', 0),
    field: 'books_count',
    align: 'center',
    sortable: true,
    style: 'width: 100px'
  },
  {
    name: 'actions',
    label: '',
    field: 'id',
    align: 'right',
    style: 'width: 100px'
  }
])

onMounted(() => {
  tagStore.getTags()
})

function openCreateDialog(): void {
  editingTag.value = null
  form.name = ''
  form.color = TAG_COLORS[0] as string
  showDialog.value = true
}

function openEditDialog(tag: Tag): void {
  editingTag.value = tag
  form.name = tag.name
  form.color = tag.color
  showDialog.value = true
}

function closeDialog(): void {
  showDialog.value = false
  editingTag.value = null
  form.name = ''
  form.color = TAG_COLORS[0] as string
}

function saveTag(): void {
  if (!form.name.trim()) return

  isSaving.value = true

  const promise = editingTag.value
    ? tagStore.putTag(editingTag.value.id, { name: form.name.trim(), color: form.color })
    : tagStore.postTag({ name: form.name.trim(), color: form.color })

  promise
    .then((result) => {
      if (result) {
        closeDialog()
      }
    })
    .finally(() => {
      isSaving.value = false
    })
}

function confirmDelete(tag: Tag): void {
  tagToDelete.value = tag
  showDeleteDialog.value = true
}

function deleteTag(): void {
  if (!tagToDelete.value) return

  isDeleting.value = true

  tagStore
    .deleteTag(tagToDelete.value.id)
    .then((success) => {
      if (success) {
        showDeleteDialog.value = false
        tagToDelete.value = null
      }
    })
    .finally(() => {
      isDeleting.value = false
    })
}
</script>

<style scoped lang="sass">
.tag-color-dot
  width: 20px
  height: 20px
  border-radius: 50%
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
