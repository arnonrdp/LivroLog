<template>
  <q-page padding>
    <q-input v-model="filter" color="primary" dense debounce="300" flat :label="$t('friends.search-for-people')">
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
    </q-input>
    <q-table
      grid
      :rows="peopleStore.getPeople"
      :columns="columns"
      row-key="id"
      :filter="filter"
      :rows-per-page-options="[20]"
      card-container-class="justify-center"
      :no-results-label="$t('friends.no-one-found')"
      class="q-mt-md bg-transparent"
    >
      <template v-slot:item="props">
        <router-link :to="props.row.username" class="q-pa-sm">
          <q-card class="full-height">
            <q-card-section class="text-center">
              <q-avatar size="100px" class="bg-transparent">
                <q-img v-if="props.row.photoURL" :src="props.row.photoURL" alt="avatar" />
                <q-icon v-else size="60px" name="person" />
              </q-avatar>
            </q-card-section>
            <q-card-section class="flex flex-center q-pt-none text-center">
              {{ props.row.name || props.row.username }}
            </q-card-section>
          </q-card>
        </router-link>
      </template>
    </q-table>
  </q-page>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import { usePeopleStore } from '@/store'
import type { QTableColumn } from 'quasar'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const peopleStore = usePeopleStore()

const columns: QTableColumn<User>[] = [
  { name: 'uid', label: 'UID', field: 'uid' },
  { name: 'displayName', label: 'Display Name', field: 'displayName', align: 'left' },
  { name: 'username', label: 'Username', field: 'username' }
]
const filter = ref('')

onMounted(() => {
  peopleStore.fetchPeople()
})

document.title = `LivroLog | ${t('menu.people')}`
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
