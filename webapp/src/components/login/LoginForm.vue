<template>
  <q-card class="text-center q-pa-md" style="width: 400px; max-width: 85vw">
    <q-card-section>
      <q-tabs v-model="tab" active-bg-color="teal" active-color="white" class="text-teal" indicator-color="transparent">
        <q-tab :label="$t('sign.signup')" name="signup" />
        <q-tab :label="$t('sign.signin')" name="signin" />
        <q-tab :label="$t('sign.recover')" name="recover" />
      </q-tabs>
    </q-card-section>
    <q-form greedy @reset="onReset" @submit="submit()">
      <q-card-section class="q-gutter-y-md">
        <q-input
          v-if="tab === 'signup'"
          v-model="displayName"
          autofocus
          dense
          :label="$t('sign.name')"
          lazy-rules
          required
          :rules="[(val) => val.length >= 2]"
        >
          <template v-slot:prepend>
            <q-icon name="person" />
          </template>
        </q-input>
        <q-input
          key="email-input"
          v-model="email"
          dense
          :label="$t('sign.mail')"
          lazy-rules
          required
          :rules="[(val, rules) => rules.email(val)]"
          type="email"
        >
          <template v-slot:prepend>
            <q-icon name="email" />
          </template>
        </q-input>
        <q-input
          v-if="tab !== 'recover'"
          v-model="password"
          dense
          :label="$t('sign.password')"
          lazy-rules
          required
          :rules="[(val) => val.length >= 6]"
          type="password"
        >
          <template v-slot:prepend>
            <q-icon name="key" />
          </template>
        </q-input>
        <q-input
          v-if="tab === 'signup'"
          v-model="passwordConfirm"
          dense
          :label="$t('sign.password-confirmation')"
          lazy-rules
          required
          :rules="[(val) => password === val]"
          type="password"
        >
          <template v-slot:prepend>
            <q-icon name="key" />
          </template>
        </q-input>
      </q-card-section>
      <q-card-actions class="column">
        <div class="q-gutter-x-md">
          <q-btn color="primary" :label="$t('sign.' + tab)" type="submit" />
          <q-btn color="primary" flat :label="$t('sign.reset')" type="reset" />
        </div>
        <q-btn
          class="q-mt-md"
          :loading="authStore.isGoogleLoading"
          :disable="authStore.isGoogleLoading"
          padding="0.5em 1em"
          color="white"
          text-color="grey-7"
          outline
          @click="googleSignIn"
        >
          <q-img alt="Google Logo" class="q-mr-xs" src="/google.svg" width="20px" height="20px" />
          <span>{{ $t('sign.sign-google', 'Continue with Google') }}</span>
        </q-btn>

        <!-- Hidden Google Sign-In button for programmatic use -->
        <div ref="googleButtonRef" style="display: none" aria-hidden="true"></div>
      </q-card-actions>
    </q-form>
  </q-card>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores'
import { GoogleAuth } from '@/utils/google-auth'
import { useQuasar } from 'quasar'
import { onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const authStore = useAuthStore()
const $q = useQuasar()
const { t } = useI18n()

const displayName = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const tab = ref('signin')
const googleButtonRef = ref<HTMLElement>()
let googleButtonElement: HTMLElement | null = null

onMounted(async () => {
  GoogleAuth.initialize().then(() => {
    // Render the hidden Google button once after initialization
    if (googleButtonRef.value) {
      GoogleAuth.renderSignInButton(googleButtonRef.value, handleGoogleCredential, {
        theme: 'outline',
        size: 'large',
        text: 'continue_with',
        shape: 'rectangular'
      })

      // Store reference to avoid querySelector
      setTimeout(() => {
        googleButtonElement = googleButtonRef.value?.querySelector('[role="button"]') as HTMLElement
      }, 100)
    }
  })
})

onUnmounted(() => {
  GoogleAuth.disableAutoSelect()
})

function submit() {
  if (tab.value === 'signup') signup()
  if (tab.value === 'signin') signin()
  if (tab.value === 'recover') resetPassword()
}

async function handleGoogleCredential(idToken: string) {
  if (authStore.isGoogleLoading) return

  await authStore.postGoogleSignIn(idToken)
}

function googleSignIn() {
  if (authStore.isGoogleLoading || !googleButtonElement) {
    if (!googleButtonElement) {
      $q.notify({ message: t('sign.google-error', 'Google Sign In not available'), type: 'negative' })
    }
    return
  }

  googleButtonElement.click()
}

function signin() {
  authStore.postAuthLogin(email.value, password.value)
}

function signup() {
  authStore
    .postAuthRegister({
      display_name: displayName.value,
      email: email.value,
      username: displayName.value.toLowerCase().replace(/\s+/g, ''),
      password: password.value,
      password_confirmation: passwordConfirm.value
    })
    .then(() => $q.notify({ icon: 'check_circle', message: t('sign.accountCreated') }))
}

function resetPassword() {
  authStore.postForgotPassword(email.value).then(() => $q.notify({ icon: 'check_circle', message: t('sign.password-reset') }))
}

function onReset() {
  displayName.value = ''
  email.value = ''
  password.value = ''
  passwordConfirm.value = ''
}
</script>
