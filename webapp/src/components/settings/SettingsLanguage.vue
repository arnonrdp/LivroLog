<template>
  <q-form class="q-gutter-md q-mb-md" @submit.prevent="saveSettings">
    <q-select v-model="locale" data-testid="language-select" emit-value :label="$t('language')" map-options :options="localeOptions">
      <template v-slot:prepend>
        <q-icon name="translate" />
      </template>
    </q-select>

    <q-select
      v-model="preferredAmazonRegion"
      data-testid="amazon-store-select"
      emit-value
      :label="$t('amazon-store-preference')"
      map-options
      :options="amazonStoreOptions"
    >
      <template v-slot:prepend>
        <q-icon name="shopping_cart" />
      </template>
      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps" :data-testid="`amazon-store-option-${scope.opt.value}`">
          <q-item-section>
            <q-item-label>{{ scope.opt.label }}</q-item-label>
            <q-item-label caption>{{ scope.opt.domain }}</q-item-label>
          </q-item-section>
        </q-item>
      </template>
      <template v-slot:selected-item="scope">
        <span data-testid="amazon-store-selected">{{ scope.opt.label }}</span>
        <span v-if="scope.opt.domain" class="text-caption text-grey-6 q-ml-sm">({{ scope.opt.domain }})</span>
      </template>
    </q-select>

    <div class="text-center">
      <q-btn color="primary" data-testid="save-language-btn" icon="save" :label="$t('save')" :loading="authStore.isLoading" type="submit" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import { AMAZON_STORES, AMAZON_STORE_GROUPS } from '@/config/amazon'
import { localeOptions } from '@/locales'
import { useAuthStore, useUserStore } from '@/stores'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale, t } = useI18n({ useScope: 'global' })

const authStore = useAuthStore()
const userStore = useUserStore()

const preferredAmazonRegion = ref(userStore.me?.preferred_amazon_region || 'US')

watch(
  () => userStore.me?.preferred_amazon_region,
  (newValue) => {
    if (newValue) {
      preferredAmazonRegion.value = newValue
    }
  },
  { immediate: true }
)

document.title = `LivroLog | ${t('language')}`

interface AmazonStoreOption {
  label: string
  value: string
  domain: string
  group: string
}

const amazonStoreOptions = computed(() => {
  const groups: Record<string, AmazonStoreOption[]> = {
    americas: [],
    europe: [],
    'asia-pacific': [],
    'middle-east-africa': []
  }

  AMAZON_STORES.forEach((store) => {
    groups[store.group]?.push({
      label: t(store.labelKey),
      value: store.code,
      domain: store.domain,
      group: store.group
    })
  })

  const result: (AmazonStoreOption | { label: string; disable: true })[] = []

  const groupOrder: (keyof typeof AMAZON_STORE_GROUPS)[] = ['americas', 'europe', 'asia-pacific', 'middle-east-africa']

  groupOrder.forEach((groupKey) => {
    const groupLabel = t(AMAZON_STORE_GROUPS[groupKey])
    const stores = groups[groupKey]
    if (stores && stores.length > 0) {
      result.push({ label: `-- ${groupLabel} --`, disable: true })
      result.push(...stores)
    }
  })

  return result
})

function saveSettings() {
  authStore.putMe({
    locale: locale.value,
    preferred_amazon_region: preferredAmazonRegion.value
  })
}
</script>
