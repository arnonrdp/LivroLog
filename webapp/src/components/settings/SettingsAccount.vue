<template>
  <div class="text-right">
    <q-btn color="negative" flat icon="logout" round @click="logout()">
      <q-tooltip>{{ $t('logout') }}</q-tooltip>
    </q-btn>
  </div>
  <q-form class="q-gutter-md q-mt-md" @submit.prevent="updateAccount">
    <q-input v-model="userStore.me.email" :label="$t('mail')" lazy-rules required :rules="[(val, rules) => rules.email(val)]">
      <template v-slot:prepend>
        <q-icon name="mail" />
      </template>
    </q-input>
    <q-input v-model="password" :label="$t('password')" lazy-rules required :rules="[(val) => val.length >= 6]" type="password">
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

const email = ref(userStore.me.email)
const password = ref('')

document.title = `LivroLog | ${t('account')}`

async function logout() {
  await authStore.postAuthLogout()
}

async function updateAccount() {
  const credential = { email: email.value, password: password.value }
  await authStore.putMe(credential)
}
</script>
