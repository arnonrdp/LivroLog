<template>
  <div>
    <div class="container"></div>
    <q-page class="q-mx-auto text-center" style="width: 1100px; max-width: 85vw">
      <section class="q-pt-lg q-pb-xl relative-position window-height">
        <div class="head">
          <TheLogo color="white" width="300px" />
          <br />
          <q-btn class="text-bold" color="white" flat :label="$t('signup-signin')" @click="login = true" />
        </div>
        <div class="description justify-center q-gutter-lg row">
          <div class="col-md-6 col-sm-12 text-white">
            <h2 class="q-mb-md text-bold">{{ $t('subtitle1') }}</h2>
            <br />
            <p class="text-subtitle1">{{ $t('subtitle2') }}</p>
          </div>
          <img :alt="$t('bookcase-model')" class="rounded-borders" src="@/assets/full_shelf.jpg" style="width: 25rem; max-width: 90vw" />
        </div>
      </section>

      <section class="text-primary">
        <h2>{{ $t('description-top') }}</h2>
        <ShowcaseBooks />
        <h2 class="q-my-none q-pb-xl">{{ $t('description-bottom') }}</h2>
      </section>

      <q-dialog v-model="login">
        <LoginForm />
      </q-dialog>
    </q-page>
  </div>
</template>

<script setup lang="ts">
import LoginForm from '@/components/login/LoginForm.vue'
import ShowcaseBooks from '@/components/login/ShowcaseBooks.vue'
import TheLogo from '@/components/login/TheLogo.vue'
import { ref } from 'vue'

const login = ref(false)
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
  justify-content: space-between;
}

.description {
  margin-top: 12rem;
}

@media screen and (max-width: 1024px) {
  .head {
    flex-direction: column;
  }

  .description {
    margin-top: 5rem;
  }
}
</style>
