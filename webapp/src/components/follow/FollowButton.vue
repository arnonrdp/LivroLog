<template>
  <q-btn
    v-if="!isSelf"
    class="follow-btn"
    :color="isFollowing ? 'grey-7' : 'primary'"
    :icon="isFollowing ? 'person_remove' : 'person_add'"
    :label="isFollowing ? $t('unfollow') : $t('follow')"
    :loading="followStore.isLoading"
    :outline="isFollowing"
    :unelevated="!isFollowing"
    @click="toggleFollow"
  >
    <q-tooltip v-if="isMutualFollow">
      {{ $t('mutual-follow') }}
    </q-tooltip>
    <q-tooltip v-else-if="isFollowedBy">
      {{ $t('follows-you') }}
    </q-tooltip>
  </q-btn>
</template>

<script setup lang="ts">
import { useAuthStore, useFollowStore } from '@/stores'
import { computed, onMounted } from 'vue'

interface Props {
  userId: string
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  dense?: boolean
  flat?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  dense: false,
  flat: false
})

const authStore = useAuthStore()
const followStore = useFollowStore()

const isSelf = computed(() => authStore.user.id === props.userId)
const isFollowing = computed(() => followStore.isFollowing(props.userId))
const isFollowedBy = computed(() => followStore.isFollowedBy(props.userId))
const isMutualFollow = computed(() => followStore.isMutualFollow(props.userId))

onMounted(async () => {
  if (!isSelf.value) {
    await followStore.getFollowStatus(props.userId)
  }
})

async function toggleFollow() {
  if (followStore.isLoading) return

  try {
    if (isFollowing.value) {
      await followStore.unfollowUser(props.userId)
    } else {
      await followStore.followUser(props.userId)
    }
  } catch (error) {
    // Error handling is done in the store
    console.error('Error toggling follow:', error)
  }
}
</script>

<style scoped>
.follow-btn {
  min-width: 100px;
}
</style>
