<template>
  <q-page padding>
    <q-tabs v-model="tab" class="text-teal">
      <q-tab name="readDates" icon="event" :label="$t('settings.books')" />
      <q-tab name="profile" icon="person" :label="$t('settings.profile')" />
      <q-tab name="language" icon="translate" :label="$t('settings.language')" />
      <q-tab name="account" icon="email" :label="$t('settings.account')" />
      <q-tab name="password" icon="lock" :label="$t('sign.password')" />
    </q-tabs>

    <q-tab-panels v-model="tab" animated swipeable transition-prev="jump-up" transition-next="jump-up">
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
import SettingsAccount from '@/components/SettingsAccount.vue'
import SettingsBooks from '@/components/SettingsBooks.vue'
import SettingsLanguage from '@/components/SettingsLanguage.vue'
import SettingsPassword from '@/components/SettingsPassword.vue'
import SettingsProfile from '@/components/SettingsProfile.vue'
import { ref } from 'vue'

const tab = ref('readDates')
</script>

<style scoped>
.q-page {
  margin: 0 auto;
  max-width: 95vw;
  width: 40rem;
}
</style>
