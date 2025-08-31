<template>
  <q-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
    <q-card style="width: 25rem; max-width: 100%">
      <q-card-section>
        <div class="text-h6">{{ $t('share-shelf') }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <div class="text-body2 text-grey-7 q-mb-md">
          {{ $t('share-shelf-description') }}
        </div>

        <div class="q-gutter-md text-center">
          <q-btn
            v-for="platform in socialPlatforms"
            :key="platform.name"
            :color="platform.color"
            flat
            :icon="`img:${platform.icon}`"
            round
            @click="shareOn(platform)"
          >
            <q-tooltip>{{ platform.label }}</q-tooltip>
          </q-btn>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { useUserStore } from '@/stores/user'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  modelValue: boolean
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

defineProps<Props>()
defineEmits<Emits>()

const { t } = useI18n()
const userStore = useUserStore()

const shareText = computed(() => {
  const bookCount = userStore.me?.books?.length || 0
  return t('share-text', { count: bookCount })
})

const shareUrl = computed(() => {
  if (!userStore.me?.username) return window.location.origin
  return `${window.location.origin}/people/${userStore.me.username}`
})

const socialPlatforms = computed(() => [
  {
    name: 'facebook',
    label: 'Facebook',
    icon: '/icons/facebook.svg',
    color: 'blue-8',
    url: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl.value)}`
  },
  {
    name: 'linkedin',
    label: 'LinkedIn',
    icon: '/icons/linkedin.svg',
    color: 'blue-7',
    url: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl.value)}`
  },
  {
    name: 'telegram',
    label: 'Telegram',
    icon: '/icons/telegram.svg',
    color: 'blue-5',
    url: `https://t.me/share/url?url=${encodeURIComponent(shareUrl.value)}&text=${encodeURIComponent(shareText.value)}`
  },
  {
    name: 'whatsapp',
    label: 'WhatsApp',
    icon: '/icons/whatsapp.svg',
    color: 'green-6',
    url: `https://wa.me/?text=${encodeURIComponent(`${shareText.value} ${shareUrl.value}`)}`
  },
  {
    name: 'x',
    label: 'X',
    icon: '/icons/x.svg',
    color: 'grey-9',
    url: `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText.value)}&url=${encodeURIComponent(shareUrl.value)}`
  }
])

function shareOn(platform: (typeof socialPlatforms.value)[0]) {
  window.open(platform.url, '_blank', 'width=600,height=400')
}
</script>
