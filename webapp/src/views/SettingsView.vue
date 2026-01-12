<template>
  <q-page padding>
    <q-tabs align="justify" class="text-teal">
      <q-route-tab exact icon="event" :label="$t('books', 0)" to="/settings/books" />
      <q-route-tab icon="label" :label="$t('tags.title')" to="/settings/tags" />
      <q-route-tab icon="person" :label="$t('profile')" to="/settings/profile" />
      <q-route-tab icon="translate" :label="$t('language')" to="/settings/language" />
      <q-route-tab icon="security" label="Account & Security" to="/settings/account" />
    </q-tabs>

    <q-tab-panels v-model="activePanel" animated swipeable transition-next="jump-up" transition-prev="jump-up">
      <q-tab-panel name="books">
        <SettingsBooks />
      </q-tab-panel>

      <q-tab-panel name="tags">
        <SettingsTags />
      </q-tab-panel>

      <q-tab-panel name="profile">
        <SettingsProfile />
      </q-tab-panel>

      <q-tab-panel name="language">
        <SettingsLanguage />
      </q-tab-panel>

      <q-tab-panel name="account">
        <SettingsAccount />
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script setup lang="ts">
import SettingsAccount from '@/components/settings/SettingsAccount.vue'
import SettingsBooks from '@/components/settings/SettingsBooks.vue'
import SettingsLanguage from '@/components/settings/SettingsLanguage.vue'
import SettingsProfile from '@/components/settings/SettingsProfile.vue'
import SettingsTags from '@/components/settings/SettingsTags.vue'
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const validTabs = ['books', 'tags', 'profile', 'language', 'account']

const activePanel = computed(() => {
  const routeTab = route.params.tab as string
  return validTabs.includes(routeTab) ? routeTab : 'books'
})

watch(
  () => route.params.tab,
  (tab) => {
    if (tab && !validTabs.includes(tab as string)) {
      router.replace('/settings/books')
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
