<template>
  <q-page padding>
    <q-tabs align="justify" class="text-teal">
      <q-route-tab exact icon="people" :label="$t('admin.users')" to="/admin/users" />
      <q-route-tab icon="menu_book" :label="$t('admin.books')" to="/admin/books" />
    </q-tabs>

    <q-tab-panels animated :model-value="activePanel" swipeable transition-next="jump-up" transition-prev="jump-up">
      <q-tab-panel name="users">
        <AdminUsers />
      </q-tab-panel>

      <q-tab-panel name="books">
        <AdminBooks />
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script setup lang="ts">
import AdminBooks from '@/components/admin/AdminBooks.vue'
import AdminUsers from '@/components/admin/AdminUsers.vue'
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const validTabs = ['users', 'books']

const activePanel = computed(() => {
  const routeTab = route.params.tab as string
  return validTabs.includes(routeTab) ? routeTab : 'users'
})

watch(
  () => route.params.tab,
  (tab) => {
    if (tab && !validTabs.includes(tab as string)) {
      router.replace('/admin/users')
    }
  },
  { immediate: true }
)
</script>

<style scoped>
.q-page {
  margin: 0 auto;
  max-width: 100vw;
  width: 56rem;
}
</style>
