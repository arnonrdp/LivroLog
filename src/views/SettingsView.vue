<template>
  <q-page padding>
    <q-tabs v-model="tab" class="text-teal">
      <q-tab name="readDates" icon="event" :label="$t('settings.books')" />
      <q-tab name="profile" icon="person" :label="$t('settings.profile')" />
      <q-tab name="account" icon="email" :label="$t('settings.account')" />
      <q-tab name="password" icon="lock" :label="$t('sign.password')" />
    </q-tabs>

    <q-tab-panels v-model="tab" animated swipeable transition-prev="jump-up" transition-next="jump-up">
      <q-tab-panel name="readDates">
        <SettingsBooksView />
      </q-tab-panel>

      <q-tab-panel name="profile">
        <SettingsProfileView />
      </q-tab-panel>

      <q-tab-panel name="account">
        <SettingsAccountView />
      </q-tab-panel>

      <q-tab-panel name="password">
        <SettingsPasswordView />
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import SettingsAccountView from './SettingsAccountView.vue'
import SettingsBooksView from './SettingsBooksView.vue'
import SettingsPasswordView from './SettingsPasswordView.vue'
import SettingsProfileView from './SettingsProfileView.vue'

const tab = ref('readDates')
</script>

<style scoped>
.q-page {
  margin: 0 auto;
  width: 32rem;
}
</style>
