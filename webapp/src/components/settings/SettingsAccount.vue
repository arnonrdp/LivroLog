<template>
  <div>
    <!-- Header with logout button and verification status -->
    <div class="row justify-between items-start q-mb-lg">
      <div>
        <q-chip
          :color="userStore.me.email_verified ? 'positive' : 'warning'"
          :icon="userStore.me.email_verified ? 'verified' : 'warning'"
          text-color="white"
        >
          {{ userStore.me.email_verified ? $t('email-verified') : $t('email-not-verified') }}
        </q-chip>
      </div>
      <q-btn color="negative" data-testid="logout-button" flat icon="logout" round @click="logout()">
        <q-tooltip>{{ $t('logout') }}</q-tooltip>
      </q-btn>
    </div>

    <!-- Account Information Form -->
    <q-card class="q-mb-lg">
      <q-card-section>
        <div class="text-h6 q-mb-md">{{ $t('account') }}</div>

        <div>
          <q-input
            class="q-mb-md"
            :label="$t('mail')"
            lazy-rules
            :model-value="userStore.me?.email || ''"
            readonly
            required
            :rules="[(val, rules) => rules.email(val)]"
          >
            <template v-slot:prepend>
              <q-icon name="mail" />
            </template>
            <template v-slot:append>
              <q-btn
                v-if="!userStore.me.email_verified"
                color="primary"
                dense
                flat
                :label="$t('verify-email')"
                :loading="authStore.isLoading"
                @click="sendVerificationEmail"
              />
            </template>
          </q-input>

          <!-- Email editing is disabled for now -->
        </div>
      </q-card-section>
    </q-card>

    <!-- Password Management -->
    <q-card class="q-mb-lg">
      <q-card-section>
        <div class="text-h6 q-mb-md">
          {{ userStore.me.has_password_set ? $t('change-password') : $t('set-password') }}
        </div>

        <div v-if="!userStore.me.has_password_set" class="q-mb-md">
          <q-banner class="bg-info text-white">
            <template v-slot:avatar>
              <q-icon name="info" />
            </template>
            {{ $t('no-password-set-info') }}
          </q-banner>
        </div>

        <q-form @submit.prevent="updatePassword">
          <!-- Only show current password field if user has a password set -->
          <q-input
            v-if="userStore.me.has_password_set"
            v-model="currentPassword"
            class="q-mb-md"
            :label="$t('current-password')"
            :rules="[isPasswordValid]"
            type="password"
          >
            <template v-slot:prepend>
              <q-icon name="lock" />
            </template>
          </q-input>

          <q-input v-model="newPassword" class="q-mb-md" :label="$t('new-password')" :rules="[isPasswordValid]" type="password">
            <template v-slot:prepend>
              <q-icon name="lock" />
            </template>
          </q-input>

          <q-input
            v-model="confirmPassword"
            class="q-mb-md"
            :label="$t('confirm-password')"
            :rules="[(val) => val === newPassword || t('passwords-must-match')]"
            type="password"
          >
            <template v-slot:prepend>
              <q-icon name="lock" />
            </template>
          </q-input>

          <div class="text-center">
            <q-btn
              color="primary"
              icon="save"
              :label="userStore.me.has_password_set ? $t('update-password') : $t('set-password')"
              :loading="authStore.isLoading"
              type="submit"
            />
          </div>
        </q-form>
      </q-card-section>
    </q-card>

    <!-- Google Account Connection -->
    <q-card>
      <q-card-section>
        <div class="text-h6 q-mb-md">{{ $t('google-account') }}</div>

        <div v-if="userStore.me.has_google_connected" class="column q-gutter-md">
          <div class="row items-center justify-between">
            <div class="row items-center q-gutter-md">
              <q-avatar size="md">
                <q-img alt="Google Logo" height="24px" src="/google.svg" width="24px" />
              </q-avatar>
              <div>
                <div class="text-body1">{{ $t('google-connected') }}</div>
                <div class="text-caption text-grey-6">{{ $t('google-account-connected-description') }}</div>
              </div>
            </div>
            <q-btn
              v-if="userStore.me.has_password_set"
              color="negative"
              :label="$t('disconnect-google')"
              :loading="authStore.isLoading"
              outline
              @click="disconnectGoogle"
            />
          </div>

          <q-banner v-if="!userStore.me.has_password_set" class="bg-warning text-dark">
            <template v-slot:avatar>
              <q-icon name="warning" />
            </template>
            {{ $t('set-password-before-disconnect') }}
          </q-banner>
        </div>

        <div v-else class="column q-gutter-md">
          <div class="row items-center q-gutter-md">
            <q-avatar color="grey-1" size="md">
              <q-img alt="Google Logo" height="24px" src="/google.svg" width="24px" />
            </q-avatar>
            <div>
              <div class="text-body1">{{ $t('google-account') }}</div>
              <div class="text-caption text-grey-6">{{ $t('google-account-not-connected-description') }}</div>
            </div>
          </div>
          <q-btn color="primary" outline @click="connectGoogle">
            <q-img alt="Google Logo" class="q-mr-xs" height="20px" src="/google.svg" width="20px" />
            <span>{{ $t('connect-google') }}</span>
          </q-btn>
        </div>

        <!-- Hidden Google Sign-In button for programmatic use -->
        <div ref="googleButtonRef" aria-hidden="true" style="display: none"></div>
      </q-card-section>
    </q-card>

    <!-- Google Email Conflict Dialog -->
    <q-dialog v-model="showEmailConflictDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">{{ $t('email-conflict-title', 'Email Conflict Detected') }}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <div class="q-mb-md">
            {{ $t('email-conflict-message', 'The Google account has a different email than your current account:') }}
          </div>

          <div class="q-mb-md">
            <div class="text-weight-medium q-mb-xs">{{ $t('current-email', 'Current Email:') }}</div>
            <div class="text-grey-8">{{ userStore.me.email }}</div>
          </div>

          <div class="q-mb-md">
            <div class="text-weight-medium q-mb-xs">{{ $t('google-email', 'Google Email:') }}</div>
            <div class="text-grey-8">{{ conflictGoogleEmail }}</div>
          </div>

          <div class="text-caption text-grey-6">
            {{ $t('email-conflict-explanation', 'What would you like to do?') }}
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-gutter-sm">
          <q-btn flat :label="$t('cancel')" @click="cancelGoogleConnection" />
          <q-btn color="primary" :label="$t('update-email', 'Update My Email')" @click="connectGoogleWithEmailUpdate" />
          <q-btn color="secondary" :label="$t('switch-account', 'Switch to Google Account')" @click="switchToGoogleAccount" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore, useUserStore } from '@/stores'
import { GoogleAuth } from '@/utils/google-auth'
import { Notify } from 'quasar'
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const authStore = useAuthStore()
const userStore = useUserStore()

const confirmPassword = ref('')
const conflictGoogleEmail = ref('')
const currentPassword = ref('')
const googleButtonRef = ref()
const newPassword = ref('')
const pendingGoogleToken = ref('')
const showEmailConflictDialog = ref(false)
let googleButtonElement: { click: () => void } | null = null

document.title = `LivroLog | ${t('account')}`

onMounted(async () => {
  authStore.getMe()

  // Initialize Google OAuth for connecting accounts
  try {
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

async function logout() {
  await authStore.postAuthLogout()
}

async function sendVerificationEmail() {
  await authStore.postAuthVerifyEmail()
}

async function disconnectGoogle() {
  await authStore.deleteAuthGoogle()
}

async function handleGoogleCredential(idToken: string) {
  if (authStore.isGoogleLoading) return

  const googleUserInfo = GoogleAuth.decodeIdToken(idToken)
  const googleEmail = googleUserInfo.email

  if (googleEmail !== userStore.me.email) {
    conflictGoogleEmail.value = googleEmail
    pendingGoogleToken.value = idToken
    showEmailConflictDialog.value = true
    return
  }

  await authStore.putAuthGoogleConnect(idToken, 'connect')
}

async function connectGoogle() {
  if (authStore.isGoogleLoading || !googleButtonElement) {
    if (!googleButtonElement) {
      Notify.create({ message: t('google-error'), type: 'negative' })
    }
    return
  }

  googleButtonElement.click()
}

function cancelGoogleConnection() {
  showEmailConflictDialog.value = false
  conflictGoogleEmail.value = ''
  pendingGoogleToken.value = ''
}

async function connectGoogleWithEmailUpdate() {
  showEmailConflictDialog.value = false
  authStore.putAuthGoogleConnect(pendingGoogleToken.value, 'update_email')
  conflictGoogleEmail.value = ''
  pendingGoogleToken.value = ''
}

async function switchToGoogleAccount() {
  showEmailConflictDialog.value = false
  authStore.postAuthGoogle(pendingGoogleToken.value)
  conflictGoogleEmail.value = ''
  pendingGoogleToken.value = ''
}

function isPasswordValid(password: string) {
  if (!password || password.length < 8) {
    return t('password-min-length')
  }
  const hasUpperCase = /[A-Z]/.test(password)
  const hasLowerCase = /[a-z]/.test(password)
  const hasDigit = /\d/.test(password)
  const hasSpecialChar = /[@$!%*?&]/.test(password)

  if (!hasUpperCase || !hasLowerCase || !hasDigit || !hasSpecialChar) {
    return t('password-requirements')
  }
  return true
}

async function updatePassword() {
  const payload: { password: string; password_confirmation: string; current_password?: string } = {
    password: newPassword.value,
    password_confirmation: confirmPassword.value
  }

  if (userStore.me.has_password_set) {
    payload.current_password = currentPassword.value
  }

  await authStore.putAuthPassword(payload).then(() => {
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
  })
}
</script>
