<template>
  <q-form @submit.prevent="preferedLanguage" class="q-gutter-md q-mb-md">
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
import { useMeta } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const updating = ref(false)

useMeta({
  title: `LivroLog | ${t('settings.language')}`,
  meta: {
    ogTitle: { name: 'og:title', content: `LivroLog | ${t('settings.language')}` },
    twitterTitle: { name: 'twitter:title', content: `LivroLog | ${t('settings.language')}` }
  }
})

const { locale } = useI18n({ useScope: 'global' })

function preferedLanguage() {
  // TODO: develop this function
  updating.value = true
  setTimeout(() => {
    updating.value = false
  }, 1000)
}
</script>
