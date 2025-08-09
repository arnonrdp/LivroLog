<template>
  <q-form class="q-gutter-md q-mb-md" @submit.prevent="updateProfile">
    <q-input v-model="displayName" :label="$t('shelfname')">
      <template v-slot:prepend>
        <q-icon name="badge" />
      </template>
    </q-input>
    <q-input v-model="username" debounce="500" :label="$t('username')" :prefix="hostname" :rules="[(val) => usernameValidator(val)]">
      <template v-slot:prepend>
        <q-icon name="link" />
      </template>
    </q-input>
    <q-item>
      <q-item-section avatar>
        <q-icon name="lock" />
      </q-item-section>
      <q-item-section>
        <q-item-label>{{ $t('private-profile') }}</q-item-label>
        <q-item-label caption>{{ $t('private-profile-description') }}</q-item-label>
      </q-item-section>
      <q-item-section side>
        <q-toggle v-model="isPrivate" />
      </q-item-section>
    </q-item>
    <div class="text-center">
      <q-btn color="primary" icon="save" :label="$t('update-profile')" :loading="authStore.isLoading" type="submit" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import router from '@/router'
import { useAuthStore, useUserStore } from '@/stores'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const authStore = useAuthStore()
const userStore = useUserStore()

const displayName = ref(authStore.user.display_name)
const username = ref(authStore.user.username)
const isPrivate = ref(authStore.user.is_private || false)
const hostname = window.location.hostname + '/'

document.title = `LivroLog | ${t('profile')}`

function usernameValidator(username: User['username']) {
  const routes = router.options.routes
  if (username === authStore.user.username) return true
  if (routes.some((r) => r.path.substr(1) === username.toLowerCase())) return false
  if (!/\w{3,20}$/.test(username)) return false
  return userStore.getCheckUsername(username.trim()).then((exists: boolean) => !exists)
}

async function updateProfile() {
  await userStore.putProfile({
    display_name: displayName.value,
    username: username.value.trim().toLowerCase(),
    is_private: isPrivate.value
  })
}
</script>
