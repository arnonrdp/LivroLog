<template>
  <q-dialog v-model="isVisible" persistent>
    <q-card class="text-center q-pa-md" style="width: 400px; max-width: 85vw">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">{{ $t(tab === 'signin' ? 'signin' : tab === 'signup' ? 'signup' : 'recover') }}</div>
        <q-space />
        <q-btn v-close-popup dense flat icon="close" round @click="close" />
      </q-card-section>

      <q-card-section>
        <q-tabs
          v-model="tab"
          active-bg-color="teal"
          active-color="white"
          class="text-teal"
          indicator-color="transparent"
        >
          <q-tab data-testid="signup-tab" :label="$t('signup')" name="signup" />
          <q-tab data-testid="signin-tab" :label="$t('signin')" name="signin" />
          <q-tab data-testid="recover-tab" :label="$t('recover')" name="recover" />
        </q-tabs>
      </q-card-section>

      <q-form greedy @reset="onReset" @submit="submit()">
        <q-card-section class="q-gutter-y-md">
          <q-input
            v-if="tab === 'signup'"
            v-model="displayName"
            autofocus
            data-testid="display-name"
            dense
            :label="$t('name')"
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
            data-testid="email"
            dense
            :label="$t('mail')"
            lazy-rules
            name="email"
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
            data-testid="password"
            dense
            :label="$t('password')"
            lazy-rules
            name="password"
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
            data-testid="password-confirmation"
            dense
            :label="$t('password-confirmation')"
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
            <q-btn color="primary" :data-testid="tab === 'signin' ? 'login-button' : tab === 'signup' ? 'register-button' : 'recover-button'" :label="$t(tab)" :loading="authStore.isLoading" type="submit" />
            <q-btn color="primary" flat :label="$t('reset')" type="reset" />
          </div>
          <q-btn
            class="q-mt-md"
            color="white"
            :disable="authStore.isGoogleLoading"
            :loading="authStore.isGoogleLoading"
            outline
            padding="0.5em 1em"
            text-color="grey-7"
            @click="googleSignIn"
          >
            <q-img alt="Google Logo" class="q-mr-xs" height="20px" src="/google.svg" width="20px" />
            <span>{{ $t('sign-google', 'Continue with Google') }}</span>
          </q-btn>

          <!-- Hidden Google Sign-In button for programmatic use -->
          <div ref="googleButtonRef" aria-hidden="true" style="display: none"></div>
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores'
import { GoogleAuth } from '@/utils/google-auth'
import { useQuasar } from 'quasar'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const authStore = useAuthStore()
const $q = useQuasar()
const { t } = useI18n()

const displayName = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const tab = ref('signin')
const googleButtonRef = ref()
let googleButtonElement: { click: () => void } | null = null

// Sync with store
const isVisible = computed({
  get: () => authStore.showAuthModal,
  set: (value) => {
    if (!value) {
      authStore.closeAuthModal()
    }
  }
})

// Watch for store tab changes
watch(
  () => authStore.authModalTab,
  (newTab) => {
    tab.value = newTab === 'register' ? 'signup' : 'signin'
  },
  { immediate: true }
)

onMounted(async () => {
  try {
    // Render the hidden Google button once after initialization
    if (googleButtonRef.value) {
      await GoogleAuth.renderSignInButton(googleButtonRef.value, handleGoogleCredential, {
        theme: 'outline',
        size: 'large',
        text: 'continue_with',
        shape: 'rectangular'
      })

      await nextTick()
      googleButtonElement = googleButtonRef.value?.querySelector('[role="button"]')
    }
  } catch (error) {
    console.error('Failed to initialize Google Auth:', error)
  }
})

onUnmounted(() => {
  GoogleAuth.disableAutoSelect()
})

function close() {
  authStore.closeAuthModal()
  onReset()
}

function submit() {
  if (tab.value === 'signup') signup()
  if (tab.value === 'signin') signin()
  if (tab.value === 'recover') resetPassword()
}

async function handleGoogleCredential(idToken: string) {
  if (authStore.isGoogleLoading) return
  await authStore.postAuthGoogle(idToken)
}

function googleSignIn() {
  if (authStore.isGoogleLoading || !googleButtonElement) {
    if (!googleButtonElement) {
      $q.notify({ message: t('google-error', 'Google Sign In not available'), type: 'negative' })
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
    .then(() => $q.notify({ message: t('account-created'), type: 'positive' }))
}

function resetPassword() {
  authStore.postAuthForgotPassword(email.value).then(() => $q.notify({ message: t('password-reset'), type: 'positive' }))
}

function onReset() {
  displayName.value = ''
  email.value = ''
  password.value = ''
  passwordConfirm.value = ''
}
</script>
