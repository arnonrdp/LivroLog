<template>
  <q-layout view="hHh lpR fFf">
    <TheHeader v-if="userStore.me.id && !['/login', '/reset-password'].includes($route.path)" />
    <q-page-container>
      <RouterView v-slot="{ Component }">
        <Transition name="fade">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import TheHeader from '@/components/TheHeader.vue'
import { useMeta } from 'quasar'
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore, useUserStore } from './stores'

const userStore = useUserStore()
const authStore = useAuthStore()
const { locale, t } = useI18n({ useScope: 'global' })

useMeta(() => ({
  title: 'LivroLog',
  meta: {
    // Primary Meta Tags
    description: { name: 'description', content: t('description') },
    keywords: { name: 'keywords', content: t('keywords') },
    author: { name: 'author', content: 'Arnon Rodrigues' },

    // Open Graph / Facebook
    ogType: { name: 'og:type', content: 'website' },
    ogTitle: { name: 'og:title', content: 'LivroLog' },
    ogDescription: { name: 'og:description', content: t('description') },
    ogImage: { name: 'og:image', content: 'https://livrolog.com/screenshot-web.jpg' as string },
    ogUrl: { name: 'og:url', content: 'https://livrolog.com/' },
    ogProperty: { name: 'og:image:alt', content: t('image-alt') },

    // Twitter
    twitterCard: { name: 'twitter:card', content: 'summary_large_image' },
    twitterTitle: { name: 'twitter:title', content: 'LivroLog' },
    twitterDescription: { name: 'twitter:description', content: t('description') },
    twitterImage: { name: 'twitter:image', content: 'https://livrolog.com/screenshot-web.jpg' },
    twitterUrl: { name: 'twitter:url', content: 'https://livrolog.com/' }
  }
}))

onMounted(() => {
  // Restore session first to get user data
  authStore.restoreSession()

  if (typeof userStore.me.locale === 'string' && userStore.me.locale) {
    locale.value = userStore.me.locale
  } else {
    locale.value = navigator.language
  }
})
</script>
