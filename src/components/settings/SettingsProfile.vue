<template>
  <q-form @submit.prevent="updateProfile" class="q-gutter-md q-mb-md">
    <q-input v-model="displayName" :label="$t('book.shelfname')">
      <template v-slot:prepend>
        <q-icon name="badge" />
      </template>
    </q-input>
    <q-input v-model="username" :label="$t('sign.username')" debounce="500" :prefix="hostname" :rules="[(val) => usernameValidator(val)]">
      <template v-slot:prepend>
        <q-icon name="link" />
      </template>
    </q-input>
    <div class="text-center">
      <q-btn :label="$t('settings.update-profile')" type="submit" color="primary" icon="save" :loading="updating" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import router from '@/router'
import { useUserStore } from '@/store'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const userStore = useUserStore()
const $q = useQuasar()
const { t } = useI18n()

const displayName = ref(userStore.getUser.displayName)
const username = ref(userStore.getUser.username)
const hostname = window.location.hostname + '/'
const updating = ref(false)

document.title = `LivroLog | ${t('settings.profile')}`

function usernameValidator(username: User['username']) {
  const routes = router.options.routes
  if (username === userStore.getUser.username) return true
  if (routes.some((r) => r.path.substr(1) === username.toLowerCase())) return false
  if (!/\w{3,20}$/.test(username)) return false
  return userStore.checkUsername(username.trim()).then((exists) => !exists)
}

function updateProfile() {
  updating.value = true
  userStore
    .updateProfile({ displayName: displayName.value, username: username.value.trim().toLowerCase() })
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.profile-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.profile-updated-error') }))
    .finally(() => (updating.value = false))
}
</script>
