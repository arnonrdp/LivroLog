<template>
  <q-card class="text-center q-pa-sm" style="width: 400px; max-width: 70vw">
    <q-card-section class="q-pa-lg">
      <q-img alt="logotipo" src="/logo.svg" width="260px" />
    </q-card-section>
    <q-card-section>
      <q-tabs active-color="white" active-bg-color="teal" class="text-teal" indicator-color="transparent" v-model="tab">
        <q-tab name="signup" :label="$t('sign.signup')" />
        <q-tab name="signin" :label="$t('sign.signin')" />
        <q-tab name="recover" :label="$t('sign.recover')" />
      </q-tabs>
    </q-card-section>
    <q-form greedy @reset="onReset" @submit="submit()">
      <q-card-section class="q-gutter-y-md">
        <q-input
          v-if="tab === 'signup'"
          autofocus
          dense
          :label="$t('sign.name')"
          lazy-rules
          required
          :rules="[(val) => val.length >= 2]"
          v-model="displayName"
        >
          <template v-slot:prepend>
            <q-icon name="person" />
          </template>
        </q-input>
        <q-input
          dense
          key="email-input"
          :label="$t('sign.mail')"
          lazy-rules
          required
          :rules="[(val, rules) => rules.email(val)]"
          v-model="email"
          type="email"
        >
          <template v-slot:prepend>
            <q-icon name="email" />
          </template>
        </q-input>
        <q-input
          v-if="tab !== 'recover'"
          dense
          :label="$t('sign.password')"
          lazy-rules
          required
          :rules="[(val) => val.length >= 6]"
          type="password"
          v-model="password"
        >
          <template v-slot:prepend>
            <q-icon name="key" />
          </template>
        </q-input>
        <q-input
          v-if="tab === 'signup'"
          dense
          :label="$t('sign.password-confirmation')"
          lazy-rules
          required
          :rules="[(val) => password === val]"
          type="password"
          v-model="passwordConfirm"
        >
          <template v-slot:prepend>
            <q-icon name="key" />
          </template>
        </q-input>
      </q-card-section>
      <q-card-actions class="column q-pb-lg">
        <div class="q-gutter-x-md">
          <q-btn color="primary" :label="$t('sign.' + tab)" type="submit" />
          <q-btn flat color="primary" :label="$t('sign.reset')" type="reset" />
        </div>
        <q-btn class="q-mt-md" padding="0.5em 1em" @click="googleSignIn">
          <q-img alt="Google Logo" class="q-mr-xs" src="/google.svg" width="25px" />
          &nbsp;
          <span>{{ $t('sign.sign-google') }}</span>
        </q-btn>
      </q-card-actions>
    </q-form>
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
