<template>
  <q-page padding>
    <q-input v-model="filter" color="primary" debounce="300" dense flat :label="$t('friends.search-for-people')">
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
    </q-input>
    <q-table
      card-container-class="justify-center"
      class="q-mt-md bg-transparent"
      :columns="columns"
      :filter="filter"
      grid
      :no-results-label="$t('friends.no-one-found')"
      row-key="id"
      :rows="userStore.people"
      v-model:pagination="pagination"
      @request="getUsers"
    >
      <template v-slot:item="props">
        <router-link class="q-pa-sm" :to="props.row.username">
          <q-card class="full-height">
            <q-card-section class="text-center">
              <q-avatar class="bg-transparent" size="100px">
                <q-img v-if="props.row.avatar" alt="avatar" :src="props.row.avatar" />
                <q-icon v-else name="person" size="60px" />
              </q-avatar>
            </q-card-section>
            <q-card-section class="flex flex-center q-pt-none text-center">
              {{ props.row.display_name || props.row.name || props.row.username }}
            </q-card-section>
          </q-card>
        </router-link>
      </template>
    </q-table>
  </q-page>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import { useUserStore } from '@/stores'
import type { QTableColumn, QTableProps } from 'quasar'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
document.title = `LivroLog | ${t('menu.people')}`

const userStore = useUserStore()

const columns: QTableColumn<User>[] = [
  { name: 'id', label: 'ID', field: 'id' },
  { name: 'display_name', label: 'Display Name', field: 'display_name', align: 'left' },
  { name: 'username', label: 'Username', field: 'username' }
]
const filter = ref('')
const pagination = ref<NonNullable<QTableProps['pagination']>>({ descending: true, page: 1, rowsNumber: 0, rowsPerPage: 20 })

onMounted(() => {
  getUsers({ pagination: pagination.value })
})

async function getUsers(props: Partial<QTableProps>) {
  if (props.pagination) {
    pagination.value = props.pagination
  }

  const params = {
    filter: filter.value || undefined,
    pagination: pagination.value
  }

  await userStore.getUsers(params).then(() => (pagination.value.rowsNumber = userStore.meta.total))
}
</script>

<style scoped>
.q-input {
  margin: 0 auto;
  max-width: 32rem;
}

.q-card {
  width: 180px;
}

a {
  color: rgba(0, 0, 0, 0.85);
  text-decoration: none;
}
</style>
