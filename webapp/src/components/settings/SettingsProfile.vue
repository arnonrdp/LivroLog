<template>
  <q-form class="q-gutter-md q-mb-md" @submit.prevent="updateProfile">
    <q-input v-model="displayName" :label="$t('book.shelfname')">
      <template v-slot:prepend>
        <q-icon name="badge" />
      </template>
    </q-input>
    <q-input v-model="username" debounce="500" :label="$t('sign.username')" :prefix="hostname" :rules="[(val) => usernameValidator(val)]">
      <template v-slot:prepend>
        <q-icon name="link" />
      </template>
    </q-input>
    <div class="text-center">
      <q-btn color="primary" icon="save" :label="$t('settings.update-profile')" :loading="authStore.isLoading" type="submit" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import router from '@/router'
import { useAuthStore, useUserStore } from '@/stores'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const authStore = useAuthStore()
const userStore = useUserStore()

const displayName = ref(authStore.user.display_name)
const username = ref(authStore.user.username)
const hostname = window.location.hostname + '/'

document.title = `LivroLog | ${t('settings.profile')}`

function usernameValidator(username: User['username']) {
  const routes = router.options.routes
  if (username === authStore.user.username) return true
  if (routes.some((r) => r.path.substr(1) === username.toLowerCase())) return false
  if (!/\w{3,20}$/.test(username)) return false
  return userStore.getCheckUsername(username.trim()).then((exists: boolean) => !exists)
}

function updateProfile() {
  userStore
    .putProfile({ display_name: displayName.value, username: username.value.trim().toLowerCase() })
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.profile-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.profile-updated-error') }))
}
</script>
