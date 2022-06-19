<template>
  <div class="text-h6">{{ $t('settings.profile') }}</div>
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
    <q-select v-model="locale" :options="localeOptions" :label="$t('settings.language')" emit-value map-options>
      <template v-slot:prepend>
        <q-icon name="translate" />
      </template>
    </q-select>
    <q-btn :label="$t('settings.update-profile')" type="submit" color="primary" icon="save" :loading="updating" />
  </q-form>
</template>

<script setup lang="ts">
import { localeOptions } from '@/i18n'
import { User } from '@/models'
import router from '@/router'
import { useUserStore } from '@/store'
import { useMeta, useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const userStore = useUserStore()
const $q = useQuasar()
const { t } = useI18n()

const displayName = ref(userStore.getUser.displayName)
const username = ref(userStore.getUser.username)
const hostname = window.location.hostname + '/'
const updating = ref(false)

useMeta({
  title: `Livrero | ${t('settings.profile')}`,
  meta: {
    ogTitle: { name: 'og:title', content: `Livrero | ${t('settings.profile')}` },
    twitterTitle: { name: 'twitter:title', content: `Livrero | ${t('settings.profile')}` }
  }
})

const { locale } = useI18n({ useScope: 'global' })

function usernameValidator(username: User['username']) {
  const routes = router.options.routes
  if (username === userStore.getUser.username) return true
  if (routes.some((r) => r.path.substr(1) === username.toLowerCase())) return false
  if (!/\w{3,20}$/.test(username)) return false
  return userStore.checkUsername(username).then((exists) => !exists)
}

function updateProfile() {
  updating.value = true
  userStore
    .updateProfile({ displayName: displayName.value, username: username.value })
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.profile-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.profile-updated-error') }))
    .finally(() => (updating.value = false))
}
</script>
