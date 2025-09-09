<template>
  <q-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
    <q-card style="width: 25rem; max-width: 100%">
      <q-card-section>
        <div class="text-h6">{{ $t('share-shelf') }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <div class="text-body2 text-grey-7 q-mb-md">{{ $t('share-shelf-description') }}</div>

        <div class="q-gutter-md text-center">
          <q-btn
            v-for="platform in socialPlatforms"
            :key="platform.name"
            :color="platform.color"
            flat
            :icon="platform.isNative ? platform.icon : `img:${platform.icon}`"
            round
            @click="shareOn(platform)"
          >
            <q-tooltip>{{ platform.label }}</q-tooltip>
          </q-btn>
        </div>

        <div class="q-mt-md text-center">
          <q-btn color="black" flat icon="content_copy" @click="copyLink">
            <q-tooltip>{{ $t('copy-link') }}</q-tooltip>
          </q-btn>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { useUserStore } from '@/stores/user'
import { useQuasar } from 'quasar'
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
const $q = useQuasar()

const shareText = computed(() => {
  const bookCount = userStore.me?.books?.length || 0
  return t('share-text', { count: bookCount })
})

function resolveShareBase(): string {
  const host = window.location.hostname
  // In dev environment, share from API so crawlers get server-rendered OG without server tweaks
  if (host === 'dev.livrolog.com') return 'https://api.dev.livrolog.com'
  // Default: use current origin; no env override
  return window.location.origin
}

const shareUrl = computed(() => {
  if (!userStore.me?.username) return resolveShareBase()
  return `${resolveShareBase()}/${userStore.me.username}`
})

const isAndroid = computed(() => $q.platform.is.android)
const isIOS = computed(() => $q.platform.is.ios)
const canUseNativeShare = computed(() => 'share' in navigator)

const socialPlatforms = computed(() =>
  [
    {
      name: 'native',
      label: t('share-native') as string,
      icon: 'share',
      color: 'primary',
      isNative: true
    },
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
      url: `https://x.com/intent/tweet?text=${encodeURIComponent(shareText.value)}&url=${encodeURIComponent(shareUrl.value)}`
    }
  ].filter((p) => p.name !== 'native' || canUseNativeShare.value)
)

function getAndroidIntent(platform: (typeof socialPlatforms.value)[0]): string | null {
  const message = `${shareText.value} ${shareUrl.value}`
  switch (platform.name) {
    case 'whatsapp':
      return `intent://send?text=${encodeURIComponent(message)}#Intent;package=com.whatsapp;scheme=whatsapp;end`
    case 'telegram':
      return `intent://share?url=${encodeURIComponent(shareUrl.value)}&text=${encodeURIComponent(shareText.value)}#Intent;package=org.telegram.messenger;scheme=tg;end`
    case 'x':
      return `intent://post?message=${encodeURIComponent(message)}#Intent;package=com.twitter.android;scheme=twitter;end`
    default:
      return null
  }
}

function getIOSDeepLink(platform: (typeof socialPlatforms.value)[0]): string | null {
  const message = `${shareText.value} ${shareUrl.value}`
  switch (platform.name) {
    case 'whatsapp':
      return `whatsapp://send?text=${encodeURIComponent(message)}`
    case 'telegram':
      return `tg://share?url=${encodeURIComponent(shareUrl.value)}&text=${encodeURIComponent(shareText.value)}`
    case 'x':
      // The X app still handles the legacy twitter:// scheme
      return `twitter://post?message=${encodeURIComponent(message)}`
    default:
      return null
  }
}

function copyLink() {
  if (navigator?.clipboard?.writeText) {
    navigator.clipboard
      .writeText(shareUrl.value)
      .then(() => {
        $q.notify?.({ type: 'positive', message: t('copied') as string })
      })
      .catch(() => {
        $q.notify?.({ type: 'negative', message: t('error-occurred') as string })
      })
  } else {
    const el = document.createElement('textarea')
    el.value = shareUrl.value
    el.setAttribute('readonly', '')
    el.style.position = 'absolute'
    el.style.left = '-9999px'
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    $q.notify?.({ type: 'positive', message: t('copied') as string })
  }
}

function shareOn(platform: (typeof socialPlatforms.value)[0]) {
  // Use native share API if available and requested
  if (platform.isNative && navigator.share) {
    navigator.share({
      title: 'LivroLog',
      text: shareText.value,
      url: shareUrl.value
    })
    return
  }

  // For mobile platforms, try deep links first with fallback
  if ((isAndroid.value || isIOS.value) && platform.name !== 'facebook' && platform.name !== 'linkedin') {
    let deepLink: string | null = null

    if (isAndroid.value) {
      deepLink = getAndroidIntent(platform)
    } else if (isIOS.value) {
      deepLink = getIOSDeepLink(platform)
    }

    if (deepLink) {
      // Set up fallback to web version if app doesn't open
      const fallbackTimer = window.setTimeout(() => {
        window.open(platform.url, '_blank', 'width=600,height=400')
      }, 1000)

      // Try to open the app
      window.location.href = deepLink

      // Clear fallback if app opens successfully
      window.setTimeout(() => window.clearTimeout(fallbackTimer), 1500)
      return
    }
  }

  // Default: use Universal Links (will open app if installed on mobile)
  window.open(platform.url, '_blank', 'width=600,height=400')
}
</script>
