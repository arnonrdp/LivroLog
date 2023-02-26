<template>
  <q-form @submit.prevent="saveLocale" class="q-gutter-md q-mb-md">
    <q-select v-model="locale" :options="localeOptions" :label="$t('settings.language')" emit-value map-options>
      <template v-slot:prepend>
        <q-icon name="translate" />
      </template>
    </q-select>
    <div class="text-center">
      <q-btn :label="$t('settings.save')" type="submit" color="primary" icon="save" :loading="updating" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import { localeOptions } from '@/i18n'
import { useUserStore } from '@/store'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale, t } = useI18n({ useScope: 'global' })
const userStore = useUserStore()
const updating = ref(false)

document.title = `LivroLog | ${t('settings.language')}`

function saveLocale() {
  userStore.updateLocale(locale.value).then(() => {
    updating.value = false
  })
}
</script>
