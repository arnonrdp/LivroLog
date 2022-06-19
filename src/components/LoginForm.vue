<template>
  <q-card class="q-mx-auto">
    <q-card-section>
      <img src="/logo.svg" alt="logotipo" />
    </q-card-section>
    <q-tabs v-model="tab" class="text-teal">
      <q-tab name="signup" :label="$t('sign.signup')" />
      <q-tab name="signin" :label="$t('sign.signin')" />
      <q-tab name="recover" :label="$t('sign.recover')" />
    </q-tabs>
    <q-form @submit="submit()" @reset="onReset" class="q-gutter-md q-ma-sm">
      <q-input dense autofocus v-if="tab === 'signup'" v-model="displayName" type="text" :label="$t('sign.name')" required />
      <q-input
        dense
        key="email-input"
        v-model="email"
        type="email"
        :label="$t('sign.mail')"
        :rules="[(val) => /^\S+@\S+\.\S+$/.test(val)]"
        lazy-rules
        required
      />
      <q-input
        dense
        v-if="tab !== 'recover'"
        v-model="password"
        type="password"
        :label="$t('sign.password')"
        :rules="[(val) => val.length >= 6]"
        lazy-rules
        required
      />
      <q-input
        dense
        v-if="tab === 'signup'"
        v-model="passwordConfirm"
        type="password"
        :label="$t('sign.password-confirmation')"
        :rules="[(val) => password === val]"
        lazy-rules
        required
      />
      <div class="q-pt-md">
        <q-btn type="submit" :label="$t('sign.' + tab)" color="primary" />
        <q-btn flat type="reset" :label="$t('sign.reset')" color="primary" class="q-ml-sm" />
      </div>
    </q-form>
    <q-card-section>
      <q-separator />
    </q-card-section>
    <q-btn @click="googleSignIn" class="btn-google">
      <img src="/google.svg" alt="" />
      &nbsp;
      <span>{{ $t('sign.sign-google') }}</span>
    </q-btn>
  </q-card>
</template>

<script setup lang="ts">
import { useAuthStore, useRegisterStore } from '@/store'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const authStore = useAuthStore()
const registerStore = useRegisterStore()
const $q = useQuasar()
const { t } = useI18n()

const displayName = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const tab = ref('signin')

function submit() {
  if (tab.value === 'signup') signup()
  if (tab.value === 'signin') signin()
  if (tab.value === 'recover') resetPassword()
}

function googleSignIn() {
  authStore.googleSignIn().catch((error: string) => $q.notify({ icon: 'error', message: authErrors[error] }))
}

function signin() {
  authStore.login(email.value, password.value).catch((error: string) => $q.notify({ icon: 'error', message: authErrors[error] }))
}

function signup() {
  registerStore
    .signup(displayName.value, email.value, password.value)
    .then(() => $q.notify({ icon: 'check_circle', message: t('sign.accountCreated') }))
    .catch((error: string) => $q.notify({ icon: 'error', message: authErrors[error] }))
}

function resetPassword() {
  registerStore
    .resetPassword(email.value)
    .then(() => $q.notify({ icon: 'check_circle', message: t('sign.password-reset') }))
    .catch((error: string) => $q.notify({ icon: 'error', message: authErrors[error] }))
}

function onReset() {
  displayName.value = ''
  email.value = ''
  password.value = ''
  passwordConfirm.value = ''
}

const authErrors: { [key: string]: string } = {
  'auth/email-already-in-use': t('sign.auth-email-already-in-use'),
  'auth/incorrect-email-or-password': t('sign.auth-incorrect-email-or-password'),
  'auth/internal-error': t('sign.auth-internal-error'),
  'auth/invalid-email': t('sign.auth-invalid-email'),
  'auth/missing-email': t('sign.auth-missing-email'),
  'auth/phone-number-already-exists': t('sign.auth-phone-number-already-exists'),
  'auth/popup-closed-by-user': t('sign.auth-popup-closed-by-user'),
  'auth/user-not-found': t('sign.auth-user-not-found'),
  'auth/weak-password': t('sign.auth-weak-password'),
  'auth/wrong-password': t('sign.auth-wrong-password')
}
</script>

<style scoped>
.q-card {
  /* margin: calc((100vh - 500px) / 2) auto; */
  max-width: 400px;
  padding: 2em;
}

img[alt='logotipo'] {
  width: 15em;
}

.btn-google {
  background-color: #fff;
  margin-top: 1em;
  padding: 0.5em 1em;
}

.btn-google img {
  margin-right: 0.5em;
}

.q-field {
  padding-bottom: 0;
}
</style>
