<template>
  <q-form class="q-gutter-md q-mb-md" @submit.prevent="saveLocale">
    <q-select v-model="locale" emit-value :label="$t('settings.language')" map-options :options="localeOptions">
      <template v-slot:prepend>
        <q-icon name="translate" />
      </template>
    </q-select>
    <div class="text-center">
      <q-btn color="primary" icon="save" :label="$t('settings.save')" :loading="authStore.isLoading" type="submit" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import { localeOptions } from '@/locales'
import { useAuthStore, useUserStore } from '@/stores'
import { useI18n } from 'vue-i18n'

const { locale, t } = useI18n({ useScope: 'global' })

const authStore = useAuthStore()
const userStore = useUserStore()

document.title = `LivroLog | ${t('settings.language')}`

function saveLocale() {
  userStore.putProfile({ locale: locale.value })
}
</script>
