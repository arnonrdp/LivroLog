<template>
  <div>
    <div class="container"></div>
    <q-page class="q-mx-auto text-center" style="width: 1100px; max-width: 85vw">
      <section class="q-pt-lg q-pb-xl relative-position window-height">
        <div class="head">
          <TheLogo color="white" width="300px" />
        </div>
        <div class="reset-form-container">
          <q-card class="text-center q-pa-md" style="width: 400px; max-width: 85vw">
            <q-card-section>
              <h5 class="q-my-md">{{ $t('reset-password') }}</h5>
            </q-card-section>
            <q-form greedy @submit="resetPassword">
              <q-card-section class="q-gutter-y-md">
                <q-input
                  v-model="password"
                  dense
                  :label="$t('new-password')"
                  lazy-rules
                  required
                  :rules="[(val) => val.length >= 6 || $t('password-min-length')]"
                  type="password"
                >
                  <template v-slot:prepend>
                    <q-icon name="key" />
                  </template>
                </q-input>
                <q-input
                  v-model="passwordConfirmation"
                  dense
                  :label="$t('password-confirmation')"
                  lazy-rules
                  required
                  :rules="[(val) => password === val || $t('passwords-dont-match')]"
                  type="password"
                >
                  <template v-slot:prepend>
                    <q-icon name="key" />
                  </template>
                </q-input>
              </q-card-section>
              <q-card-actions>
                <q-btn class="full-width" color="primary" :label="$t('reset-password')" :loading="authStore.isLoading" type="submit" />
              </q-card-actions>
            </q-form>
          </q-card>
        </div>
      </section>
    </q-page>
  </div>
</template>

<script setup lang="ts">
import TheLogo from '@/components/login/TheLogo.vue'
import { useAuthStore } from '@/stores'
import { useQuasar } from 'quasar'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const authStore = useAuthStore()
const $q = useQuasar()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const password = ref('')
const passwordConfirmation = ref('')
const token = ref('')
const email = ref('')

onMounted(() => {
  token.value = route.query.token as string
  email.value = route.query.email as string

  if (!token.value || !email.value) {
    $q.notify({ message: t('invalid-reset-link'), type: 'negative' })
    router.push('/login')
  }
})

async function resetPassword() {
  await authStore
    .postAuthResetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value
    })
    .then(() => router.push('/login'))
}
</script>

<style scoped>
.q-page {
  font-family: 'SF Pro', sans-serif;
}

.container {
  background-image: url('@/assets/bg_library.jpeg');
  background-position: top center;
  background-repeat: no-repeat;
  background-size: cover;
  clip-path: polygon(0 0, 0 93%, 100% 85%, 100% 0);
  -webkit-clip-path: polygon(0 0, 0 93%, 100% 85%, 100% 0);
  filter: blur(3px);
  -webkit-filter: blur(3px);
  height: 100vh;
  position: absolute;
  top: 0;
  width: 100%;
  z-index: -1;
}

.head {
  align-items: center;
  display: flex;
  justify-content: center;
  margin-bottom: 2rem;
}

.reset-form-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
}

@media screen and (max-width: 1024px) {
  .reset-form-container {
    min-height: 50vh;
  }
}
</style>
