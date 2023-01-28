<template>
  <q-layout>
    <TheHeader v-if="userStore.getUser.uid && $route.path !== '/login'" />
    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import TheHeader from '@/components/TheHeader.vue'
import { useMeta } from 'quasar'
import { useI18n } from 'vue-i18n'
import { useUserStore } from './store'

const userStore = useUserStore()
const { locale, t } = useI18n({ useScope: 'global' })

locale.value = userStore.getUser.locale
useMeta(() => ({
  title: 'LivroLog',
  meta: {
    // Primary Meta Tags
    description: { name: 'description', content: t('meta.description') },
    keywords: { name: 'keywords', content: t('meta.keywords') },
    author: { name: 'author', content: 'Arnon Rodrigues' },

    // Open Graph / Facebook
    ogType: { name: 'og:type', content: 'website' },
    ogTitle: { name: 'og:title', content: 'LivroLog' },
    ogDescription: { name: 'og:description', content: t('meta.description') },
    ogImage: { name: 'og:image', content: 'https://livrolog.com/main.jpg' },
    ogUrl: { name: 'og:url', content: 'https://livrolog.com/' },
    ogProperty: { name: 'og:image:alt', content: t('meta.image-alt') },

    // Twitter
    twitterCard: { name: 'twitter:card', content: 'summary_large_image' },
    twitterTitle: { name: 'twitter:title', content: 'LivroLog' },
    twitterDescription: { name: 'twitter:description', content: t('meta.description') },
    twitterImage: { name: 'twitter:image', content: 'https://livrolog.com/main.jpg' },
    twitterUrl: { name: 'twitter:url', content: 'https://livrolog.com/' }
  }
}))
</script>

<style>
html {
  font-size: 16px;
}
@media screen and (max-width: 720px) {
  html {
    font-size: 15px;
  }
}
@media screen and (max-width: 640px) {
  html {
    font-size: 14px;
  }
}
@media screen and (max-width: 560px) {
  html {
    font-size: 13px;
  }
}
@media screen and (max-width: 480px) {
  html {
    font-size: 12px;
  }
}
@media screen and (max-width: 400px) {
  html {
    font-size: 11px;
  }
}
@media screen and (max-width: 320px) {
  html {
    font-size: 10px;
  }
}
@media screen and (max-width: 240px) {
  html {
    font-size: 9px;
  }
}
body {
  background: var(--q-background);
}
</style>
