<template>
  <div class="text-right">
    <q-btn color="negative" flat icon="logout" round @click="logout()">
      <q-tooltip>{{ $t('sign.logout') }}</q-tooltip>
    </q-btn>
  </div>
  <q-form @submit.prevent="updateAccount" class="q-gutter-md q-mt-md">
    <q-input v-model="userStore.getUser.email" :label="$t('sign.mail')" lazy-rules required :rules="[(val, rules) => rules.email(val)]">
      <template v-slot:prepend>
        <q-icon name="mail" />
      </template>
    </q-input>
    <q-input v-model="password" type="password" :label="$t('sign.password')" lazy-rules required :rules="[(val) => val.length >= 6]">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <div class="text-center">
      <q-btn :label="$t('settings.update-account')" type="submit" color="primary" icon="save" :loading="updating" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import { useAuthStore, useUserStore } from '@/store'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const authStore = useAuthStore()
const userStore = useUserStore()

const email = ref(userStore.getUser.email)
const password = ref('')
const updating = ref(false)

document.title = `LivroLog | ${t('settings.account')}`

function logout() {
  authStore.logout()
}

function updateAccount() {
  updating.value = true
  const credential = { email: email.value, password: password.value }

  userStore
    .updateAccount(credential)
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.account-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.account-updated-error') }))
    .finally(() => (updating.value = false))
}
</script>
