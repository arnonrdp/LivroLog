<template>
  <div class="text-right">
    <q-btn color="negative" flat icon="logout" round @click="logout()">
      <q-tooltip>{{ $t('sign.logout') }}</q-tooltip>
    </q-btn>
  </div>
  <q-form class="q-gutter-md q-mt-md" @submit.prevent="updateAccount">
    <q-input v-model="authStore.user.email" :label="$t('sign.mail')" lazy-rules required :rules="[(val, rules) => rules.email(val)]">
      <template v-slot:prepend>
        <q-icon name="mail" />
      </template>
    </q-input>
    <q-input v-model="password" :label="$t('sign.password')" lazy-rules required :rules="[(val) => val.length >= 6]" type="password">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <div class="text-center">
      <q-btn color="primary" icon="save" :label="$t('settings.update-account')" :loading="authStore.isLoading" type="submit" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import { useAuthStore, useUserStore } from '@/stores'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const authStore = useAuthStore()
const userStore = useUserStore()

const email = ref(authStore.user.email)
const password = ref('')

document.title = `LivroLog | ${t('settings.account')}`

function logout() {
  authStore.postAuthLogout()
}

function updateAccount() {
  const credential = { email: email.value, password: password.value }

  userStore
    .putAccount(credential)
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.account-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.account-updated-error') }))
}
</script>
