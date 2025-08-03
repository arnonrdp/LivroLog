<template>
  <q-page padding>
    <q-tabs v-model="tab" align="justify" class="text-teal">
      <q-tab icon="event" :label="$t('settings.books')" name="readDates" />
      <q-tab icon="person" :label="$t('settings.profile')" name="profile" />
      <q-tab icon="translate" :label="$t('settings.language')" name="language" />
      <q-tab icon="email" :label="$t('settings.account')" name="account" />
      <q-tab icon="lock" :label="$t('sign.password')" name="password" />
    </q-tabs>

    <q-tab-panels v-model="tab" animated swipeable transition-next="jump-up" transition-prev="jump-up">
      <q-tab-panel name="readDates">
        <SettingsBooks />
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

      <q-tab-panel name="password">
        <SettingsPassword />
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script setup lang="ts">
import SettingsAccount from '@/components/settings/SettingsAccount.vue'
import SettingsBooks from '@/components/settings/SettingsBooks.vue'
import SettingsLanguage from '@/components/settings/SettingsLanguage.vue'
import SettingsPassword from '@/components/settings/SettingsPassword.vue'
import SettingsProfile from '@/components/settings/SettingsProfile.vue'
import { ref } from 'vue'

const tab = ref('readDates')
</script>

<style scoped>
.q-page {
  margin: 0 auto;
  max-width: 100vw;
  width: 40rem;
}
</style>
