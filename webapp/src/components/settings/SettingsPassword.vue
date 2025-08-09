<template>
  <q-form class="q-gutter-md q-mt-md" @submit.prevent="updateAccount">
    <q-input v-model="oldPass" :label="$t('password')" :rules="[(val) => isPasswordValid(val)]" type="password">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <q-input v-model="newPass" :label="$t('pass1')" :rules="[(val) => isPasswordValid(val)]" type="password">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <q-input v-model="confPass" :label="$t('pass2')" :rules="[(val) => isConfirmationPassword(val)]" type="password">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <div class="text-center">
      <q-btn color="primary" icon="save" :label="$t('update-account')" :loading="authStore.isLoading" type="submit" />
    </div>
  </q-form>
</template>

<script setup lang="ts">
import { useAuthStore, useUserStore } from '@/stores'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const authStore = useAuthStore()
const userStore = useUserStore()

const email = ref(authStore.user.email)
const oldPass = ref('')
const newPass = ref('')
const confPass = ref('')

document.title = `LivroLog | ${t('password')}`

function isPasswordValid(password: string) {
  return password.length >= 6
}

function isConfirmationPassword(password: string) {
  return password === newPass.value
}

async function updateAccount() {
  const credential = { email: email.value, password: oldPass.value, newPass: newPass.value }

  await userStore.putAccount(credential)
}
</script>
