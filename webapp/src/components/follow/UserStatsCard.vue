<template>
  <q-card bordered class="user-stats-card" flat>
    <q-card-section class="text-center">
      <!-- Avatar -->
      <q-avatar class="q-mb-md" size="80px">
        <img v-if="user.avatar" :alt="user.display_name" :src="user.avatar" />
        <q-icon v-else name="person" size="40px" />
      </q-avatar>

      <!-- User Info -->
      <div class="text-h6 text-weight-medium q-mb-xs">
        {{ user.display_name }}
      </div>
      <div class="text-body2 text-grey q-mb-md">@{{ user.username }}</div>

      <!-- Shelf Name -->
      <div v-if="user.shelf_name && user.shelf_name !== user.display_name" class="text-body2 text-italic q-mb-md">"{{ user.shelf_name }}"</div>

      <!-- Follow Button -->
      <div class="q-mb-lg">
        <FollowButton :user-id="user.id" />
      </div>

      <!-- Stats -->
      <div class="row q-gutter-md justify-center">
        <!-- Books Count -->
        <div class="col-auto text-center">
          <div class="text-h6 text-weight-bold text-primary">
            {{ user.books?.length || 0 }}
          </div>
          <div class="text-body2 text-grey">
            {{ $t('books', 'Livros') }}
          </div>
        </div>

        <!-- Followers Count -->
        <div class="col-auto text-center cursor-pointer" @click="$emit('showFollowers')">
          <div class="text-h6 text-weight-bold text-primary">
            {{ user.followers_count || 0 }}
          </div>
          <div class="text-body2 text-grey">
            {{ $t('followers') }}
          </div>
        </div>

        <!-- Following Count -->
        <div class="col-auto text-center cursor-pointer" @click="$emit('showFollowing')">
          <div class="text-h6 text-weight-bold text-primary">
            {{ user.following_count || 0 }}
          </div>
          <div class="text-body2 text-grey">
            {{ $t('following') }}
          </div>
        </div>
      </div>

      <!-- Follow Status Badges -->
      <div v-if="!isSelf" class="q-mt-md">
        <q-badge v-if="isMutualFollow" class="q-mr-sm" color="positive" icon="favorite" :label="$t('mutual-follow')" />
        <q-badge v-else-if="isFollowedBy" class="q-mr-sm" color="info" icon="person" :label="$t('follows-you')" />
      </div>

      <!-- Private Account Badge -->
      <div v-if="user.is_private" class="q-mt-sm">
        <q-badge color="orange" icon="lock" label="Conta Privada" />
      </div>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import { useAuthStore, useFollowStore } from '@/stores'
import { computed, onMounted } from 'vue'
import FollowButton from './FollowButton.vue'

interface Props {
  user: User
}

defineEmits<{
  showFollowers: []
  showFollowing: []
}>()

const props = defineProps<Props>()

const authStore = useAuthStore()
const followStore = useFollowStore()

const isSelf = computed(() => authStore.user.id === props.user.id)
const isFollowing = computed(() => followStore.isFollowing(props.user.id))
const isFollowedBy = computed(() => followStore.isFollowedBy(props.user.id))
const isMutualFollow = computed(() => followStore.isMutualFollow(props.user.id))

onMounted(async () => {
  if (!isSelf.value) {
    await followStore.getFollowStatus(props.user.id)
  }
})
</script>

<style scoped>
.user-stats-card {
  max-width: 350px;
  margin: 0 auto;
}

.cursor-pointer {
  cursor: pointer;
  transition: background-color 0.2s ease;
  border-radius: 4px;
  padding: 4px 8px;
}

.cursor-pointer:hover {
  background-color: rgba(0, 0, 0, 0.04);
}
</style>
