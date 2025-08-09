<template>
  <div class="followers-list">
    <div class="text-h6 q-mb-md">
      <q-icon class="q-mr-sm" name="people" />
      {{ $t('followers') }}
      <q-badge v-if="totalFollowers > 0" class="q-ml-sm" color="primary">
        {{ totalFollowers }}
      </q-badge>
    </div>

    <div v-if="followStore.isLoading && followers.length === 0" class="text-center q-py-lg">
      <q-spinner color="primary" size="3em" />
      <div class="text-grey q-mt-md">{{ $t('loading') }}</div>
    </div>

    <div v-else-if="followers.length === 0" class="text-center q-py-xl text-grey">
      <q-icon class="q-mb-md" name="people_outline" size="4em" />
      <div class="text-h6">{{ $t('no-followers') }}</div>
      <div class="text-body2 q-mt-sm">{{ $t('no-followers-description', 'Este usuário ainda não tem seguidores') }}</div>
    </div>

    <div v-else>
      <q-list separator>
        <q-item v-for="follower in followers" :key="follower.id" class="follower-item" clickable @click="$router.push(`/${follower.username}`)">
          <q-item-section avatar>
            <q-avatar size="48px">
              <img v-if="follower.avatar" :alt="follower.display_name" :src="follower.avatar" />
              <q-icon v-else name="person" size="24px" />
            </q-avatar>
          </q-item-section>

          <q-item-section>
            <q-item-label class="text-weight-medium">
              {{ follower.display_name }}
            </q-item-label>
            <q-item-label caption>@{{ follower.username }}</q-item-label>
          </q-item-section>

          <q-item-section side>
            <FollowButton dense size="sm" :user-id="follower.id" />
          </q-item-section>
        </q-item>
      </q-list>

      <!-- Load More Button -->
      <div v-if="hasMore" class="text-center q-mt-lg">
        <q-btn color="primary" flat icon="expand_more" :label="$t('load-more')" :loading="followStore.isLoading" @click="loadMore" />
      </div>

      <!-- Loading More Indicator -->
      <div v-if="followStore.isLoading && followers.length > 0" class="text-center q-py-md">
        <q-spinner color="primary" size="2em" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useFollowStore } from '@/stores'
import { computed, onMounted, onUnmounted } from 'vue'
import FollowButton from './FollowButton.vue'

interface Props {
  userId: string
}

const props = defineProps<Props>()

const followStore = useFollowStore()

const followers = computed(() => followStore.followers)
const totalFollowers = computed(() => followStore.followersPagination.total)
const hasMore = computed(() => followStore.followersPagination.has_more)

onMounted(async () => {
  await loadFollowers(1)
})

onUnmounted(() => {
  followStore.clearFollowers()
})

async function loadFollowers(page: number = 1) {
  try {
    await followStore.getFollowers(props.userId, page)
  } catch (error) {
    console.error('Error loading followers:', error)
  }
}

async function loadMore() {
  const nextPage = followStore.followersPagination.current_page + 1
  await loadFollowers(nextPage)
}
</script>

<style scoped>
.followers-list {
  max-width: 600px;
  margin: 0 auto;
}

.follower-item {
  border-radius: 8px;
  margin-bottom: 4px;
  transition: background-color 0.2s ease;
}

.follower-item:hover {
  background-color: rgba(0, 0, 0, 0.04);
}
</style>
