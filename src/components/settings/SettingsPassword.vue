<template>
  <q-form @submit.prevent="updateAccount" class="q-gutter-md q-mt-md">
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
    <div class="text-center">
      <q-btn :label="$t('settings.update-account')" type="submit" color="primary" icon="save" :loading="userStore.isLoading" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import { useUserStore } from '@/store'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const userStore = useUserStore()

const email = ref(userStore.getUser.email)
const oldPass = ref('')
const newPass = ref('')
const confPass = ref('')

document.title = `LivroLog | ${t('sign.password')}`

function isPasswordValid(password: string) {
  return password.length >= 6
}

function isConfirmationPassword(password: string) {
  return password === newPass.value
}

function updateAccount() {
  const credential = { email: email.value, password: oldPass.value, newPass: newPass.value }

  userStore
    .updateAccount(credential)
    .then(() => $q.notify({ icon: 'check_circle', message: t('settings.account-updated') }))
    .catch(() => $q.notify({ icon: 'error', message: t('settings.account-updated-error') }))
}
</script>
