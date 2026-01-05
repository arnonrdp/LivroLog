<template>
  <q-table
    v-model:pagination="pagination"
    :columns="columns"
    :dense="$q.screen.lt.md"
    :filter="filter"
    flat
    :loading="isLoading"
    row-key="id"
    :rows="users"
    :rows-per-page-options="[10, 25, 50]"
    @request="onRequest"
  >
    <template v-slot:top>
      <q-input v-model="filter" class="q-mb-md" clearable debounce="300" dense :placeholder="$t('admin.search-users')" style="width: 300px">
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
      </q-input>
    </template>

    <template v-slot:body-cell-user_info="props">
      <q-td :props="props">
        <div>
          <span class="text-weight-medium">{{ props.row.display_name }}</span>
          <span class="text-grey-6"> &bull; </span>
          <router-link :to="`/${props.row.username}`">@{{ props.row.username }}</router-link>
        </div>
        <div class="text-caption text-grey-6">{{ props.row.email }}</div>
      </q-td>
    </template>

    <template v-slot:body-cell-role="props">
      <q-td :props="props">
        <q-badge class="text-capitalize" :color="props.row.role === 'admin' ? 'primary' : 'grey'" :label="props.row.role" />
      </q-td>
    </template>

    <template v-slot:body-cell-last_activity_at="props">
      <q-td :props="props">
        <template v-if="props.row.last_activity">
          <div>{{ formatActivityType(props.row.last_activity.type) }}</div>
          <div v-if="props.row.last_activity.subject_name" class="text-caption text-grey-6 ellipsis" style="max-width: 150px">
            {{ props.row.last_activity.subject_name }}
          </div>
          <div class="text-caption text-grey-6">{{ formatRelativeDate(props.row.last_activity.created_at) }}</div>
        </template>
        <span v-else class="text-grey-5">-</span>
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
        {{ $t('admin.no-users-found') }}
      </div>
    </template>
  </q-table>

  <!-- Edit Dialog -->
  <q-dialog v-model="editDialog" persistent>
    <q-card style="min-width: 500px">
      <q-card-section>
        <div class="text-h6">{{ $t('admin.edit-user') }}</div>
      </q-card-section>

      <q-card-section v-if="editForm.avatar" class="q-pt-none text-center">
        <q-avatar size="80px">
          <img :src="editForm.avatar" />
        </q-avatar>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input v-model="editForm.display_name" dense :label="$t('admin.display-name')" class="q-mb-sm" />
        <q-input v-model="editForm.username" dense :label="$t('admin.username')" class="q-mb-sm" />
        <q-input v-model="editForm.email" dense :label="$t('admin.email')" type="email" class="q-mb-sm" />
        <q-select
          v-model="editForm.role"
          dense
          emit-value
          :label="$t('admin.role')"
          map-options
          :options="roleOptions"
          class="q-mb-sm"
        />
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup flat :label="$t('cancel')" />
        <q-btn color="primary" flat :label="$t('save')" :loading="isSaving" @click="saveUser" />
      </q-card-actions>
    </q-card>
  </q-dialog>

  <!-- Delete Confirmation Dialog -->
  <q-dialog v-model="deleteDialog" persistent>
    <q-card>
      <q-card-section class="row items-center">
        <q-icon color="negative" name="warning" size="2em" class="q-mr-sm" />
        <span>{{ $t('admin.confirm-delete-user', { name: userToDelete?.display_name }) }}</span>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup flat :label="$t('cancel')" />
        <q-btn color="negative" flat :label="$t('admin.delete')" :loading="isDeleting" @click="deleteUser" />
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

interface LastActivity {
  type: 'book_added' | 'book_started' | 'book_read' | 'review_written' | 'user_followed'
  subject_name: string | null
  created_at: string
}

interface AdminUser {
  id: string
  display_name: string
  username: string
  email: string
  avatar: string | null
  role: string
  books_count: number
  followers_count: number
  following_count: number
  created_at: string
  last_activity: LastActivity | null
}

interface EditForm {
  id: string
  display_name: string
  username: string
  email: string
  avatar: string | null
  role: string
}

const $q = useQuasar()
const { t, locale } = useI18n()

const users = ref<AdminUser[]>([])
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
const userToDelete = ref<AdminUser | null>(null)
const editForm = ref<EditForm>({
  id: '',
  display_name: '',
  username: '',
  email: '',
  avatar: null,
  role: 'user'
})

const roleOptions = [
  { label: 'User', value: 'user' },
  { label: 'Admin', value: 'admin' }
]

const columns = computed<QTableColumn<AdminUser>[]>(() => [
  { name: 'user_info', label: t('admin.display-name'), field: 'display_name', align: 'left', sortable: true },
  { name: 'role', label: t('admin.role'), field: 'role', align: 'center', sortable: true },
  { name: 'books_count', label: t('admin.books-count'), field: 'books_count', align: 'center', sortable: true },
  { name: 'last_activity_at', label: t('admin.last-activity'), field: 'last_activity', align: 'left', sortable: true },
  { name: 'created_at', label: t('admin.registered-at'), field: 'created_at', align: 'left', sortable: true },
  { name: 'actions', label: '', field: () => null, align: 'center', style: 'width: 100px' }
])

const activityLabels: Record<string, string> = {
  book_added: t('admin.activity.book-added'),
  book_started: t('admin.activity.book-started'),
  book_read: t('admin.activity.book-read'),
  review_written: t('admin.activity.review-written'),
  user_followed: t('admin.activity.user-followed')
}

function formatActivityType(type: string): string {
  return activityLabels[type] || type
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

function formatRelativeDate(dateString: string): string {
  if (!dateString) return '-'
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return t('admin.activity.just-now')
  if (diffMins < 60) return t('admin.activity.minutes-ago', { count: diffMins })
  if (diffHours < 24) return t('admin.activity.hours-ago', { count: diffHours })
  if (diffDays < 7) return t('admin.activity.days-ago', { count: diffDays })

  return date.toLocaleDateString(locale.value, { month: 'short', day: 'numeric' })
}

function fetchUsers(page: number, perPage: number, filterValue: string, sortBy: string, descending: boolean) {
  isLoading.value = true
  api
    .get('/admin/users', {
      params: {
        page,
        per_page: perPage,
        filter: filterValue || undefined,
        sort_by: sortBy,
        sort_desc: descending
      }
    })
    .then((response) => {
      users.value = response.data.data
      pagination.value.rowsNumber = response.data.meta.total
      pagination.value.page = response.data.meta.current_page
      pagination.value.rowsPerPage = response.data.meta.per_page
    })
    .catch((error) => {
      console.error('Failed to fetch users:', error)
    })
    .finally(() => {
      isLoading.value = false
    })
}

function onRequest(props: Parameters<NonNullable<QTableProps['onRequest']>>[0]) {
  const { page, rowsPerPage, sortBy, descending } = props.pagination
  pagination.value.sortBy = sortBy ?? 'created_at'
  pagination.value.descending = descending ?? true
  fetchUsers(page, rowsPerPage, filter.value, pagination.value.sortBy, pagination.value.descending)
}

function openEditDialog(user: AdminUser) {
  editForm.value = {
    id: user.id,
    display_name: user.display_name || '',
    username: user.username || '',
    email: user.email || '',
    avatar: user.avatar,
    role: user.role || 'user'
  }
  editDialog.value = true
}

function saveUser() {
  isSaving.value = true
  api
    .put(`/users/${editForm.value.id}`, {
      display_name: editForm.value.display_name,
      username: editForm.value.username,
      email: editForm.value.email,
      role: editForm.value.role
    })
    .then(() => {
      Notify.create({ message: t('admin.user-updated'), type: 'positive' })
      editDialog.value = false
      fetchUsers(pagination.value.page, pagination.value.rowsPerPage, filter.value, pagination.value.sortBy, pagination.value.descending)
    })
    .catch((error) => {
      Notify.create({ message: error.response?.data?.message || t('admin.error-updating'), type: 'negative' })
    })
    .finally(() => {
      isSaving.value = false
    })
}

function confirmDelete(user: AdminUser) {
  userToDelete.value = user
  deleteDialog.value = true
}

function deleteUser() {
  if (!userToDelete.value) return

  isDeleting.value = true
  api
    .delete(`/users/${userToDelete.value.id}`)
    .then(() => {
      Notify.create({ message: t('admin.user-deleted'), type: 'positive' })
      deleteDialog.value = false
      userToDelete.value = null
      fetchUsers(pagination.value.page, pagination.value.rowsPerPage, filter.value, pagination.value.sortBy, pagination.value.descending)
    })
    .catch((error) => {
      Notify.create({ message: error.response?.data?.message || t('admin.error-deleting'), type: 'negative' })
    })
    .finally(() => {
      isDeleting.value = false
    })
}

onMounted(() => {
  fetchUsers(1, pagination.value.rowsPerPage, '', pagination.value.sortBy, pagination.value.descending)
})
</script>

<style scoped lang="sass">
a
  color: var(--q-primary)
  text-decoration: none
  &:hover
    text-decoration: underline
</style>
