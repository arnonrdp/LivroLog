<template>
  <q-layout view="hHh lpR fFf">
    <TheHeader v-if="authStore.user.id && !['/login', '/reset-password'].includes($route.path)" />
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
import { useRoute } from 'vue-router'
import { useAuthStore } from './stores'

const authStore = useAuthStore()
const route = useRoute()
const { locale, t } = useI18n({ useScope: 'global' })

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
    ogImage: { name: 'og:image', content: 'https://livrolog.com/main.jpg' as string },
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

onMounted(() => {
  if (typeof authStore.user.locale === 'string') {
    locale.value = authStore.user.locale
  } else {
    locale.value = navigator.language
  }
})
</script>
