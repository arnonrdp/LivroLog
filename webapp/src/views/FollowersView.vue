<template>
  <q-page padding>
    <div class="row justify-center">
      <div class="col-12 col-md-8 col-lg-6">
        <!-- Header with back button -->
        <div class="row items-center q-mb-lg">
          <q-btn flat round icon="arrow_back" @click="$router.back()" class="q-mr-md" />
          <div class="text-h5">
            {{ user.display_name }}
          </div>
        </div>

        <!-- User Info Card -->
        <q-card flat bordered class="q-mb-lg">
          <q-card-section class="text-center">
            <q-avatar size="60px" class="q-mb-md">
              <img v-if="user.avatar" :src="user.avatar" :alt="user.display_name" />
              <q-icon v-else name="person" size="30px" />
            </q-avatar>
            <div class="text-h6">{{ user.display_name }}</div>
            <div class="text-body2 text-grey">@{{ user.username }}</div>
          </q-card-section>
        </q-card>

        <!-- Followers List -->
        <FollowersList :user-id="userId" />
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import FollowersList from '@/components/follow/FollowersList.vue'
import type { User } from '@/models'
import { usePeopleStore } from '@/stores'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const peopleStore = usePeopleStore()

const user = ref({} as User)
const userId = ref('')

onMounted(async () => {
  const username = route.params.username as string
  if (username) {
    await peopleStore.getUserByIdentifier(username)
    user.value = peopleStore.person
    userId.value = user.value.id

    document.title = `${user.value.display_name} - Seguidores | LivroLog`
  }
})
</script>
