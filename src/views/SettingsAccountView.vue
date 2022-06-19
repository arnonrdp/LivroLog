<template>
  <div class="text-h6">{{ $t('settings.account') }}</div>
  <q-form @submit.prevent="updateAccount" class="q-gutter-md q-mt-md">
    <q-input v-model="userStore.getUser.email" :label="$t('sign.mail')" :rules="[(val) => isEmailValid(val)]" required>
      <template v-slot:prepend>
        <q-icon name="mail" />
      </template>
    </q-input>
    <q-input v-model="oldPass" type="password" :label="$t('sign.password')" :rules="[(val) => isPasswordValid(val)]">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <q-input v-model="newPass" type="password" :label="$t('settings.pass1')" :rules="[(val) => isPasswordValid(val)]">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <q-input v-model="confPass" type="password" :label="$t('settings.pass2')" :rules="[(val) => isConfirmationPassword(val)]">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <q-btn :label="$t('settings.update-account')" type="submit" color="primary" icon="save" :loading="updating" />
  </q-form>
</template>

<script setup lang="ts">
import { User } from '@/models'
import { useUserStore } from '@/store'
import { useMeta, useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const userStore = useUserStore()
const $q = useQuasar()
const { t } = useI18n()

const email = ref(userStore.getUser.email)
const oldPass = ref('')
const newPass = ref('')
const confPass = ref('')
const updating = ref(false)

useMeta({
  title: `Livrero | ${t('settings.account')}`,
  meta: {
    ogTitle: { name: 'og:title', content: `Livrero | ${t('settings.account')}` },
    twitterTitle: { name: 'twitter:title', content: `Livrero | ${t('settings.account')}` }
  }
})

function isEmailValid(mail: User['email']) {
  if (mail === email.value) return true
  if (
    !/^(([^<>()\\[\]\\.,;:\s@"]+(\.[^<>()\\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(
      mail
    )
  )
    return false
  return userStore.checkEmail(mail).then((exists) => !exists)
}

function isPasswordValid(password: string) {
  return password.length >= 6
}

function isConfirmationPassword(password: string) {
  return password === newPass.value
}

function updateAccount() {
  updating.value = true
  const credential = { email: email.value, password: oldPass.value, newPass: newPass.value }

  userStore
    .updateAccount(credential)
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.account-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.account-updated-error') }))
    .finally(() => (updating.value = false))
}
</script>
