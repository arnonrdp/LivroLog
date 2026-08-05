<template>
  <q-layout view="hHh lpR fFf">
    <TheHeader v-if="$route.path !== '/reset-password'" />
    <q-page-container>
      <RouterView v-slot="{ Component }">
        <Transition name="fade">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </q-page-container>
    <AuthModal />
  </q-layout>
</template>

<script setup lang="ts">
import AuthModal from '@/components/auth/AuthModal.vue'
import TheHeader from '@/components/TheHeader.vue'
import { useMeta } from 'quasar'
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore, useUserStore } from './stores'

const userStore = useUserStore()
const authStore = useAuthStore()
const { locale, t } = useI18n({ useScope: 'global' })

// Only the locale-reactive tags live here. The Open Graph/Twitter defaults come
// from index.html, which already emits them with property= (useMeta emitted them
// with name=, which Facebook and WhatsApp ignore). Routes that need their own
// preview override them individually.
useMeta(() => ({
  title: 'LivroLog',
  meta: {
    description: { name: 'description', content: t('description') },
    keywords: { name: 'keywords', content: t('keywords') },
    author: { name: 'author', content: 'Arnon Rodrigues' }
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
