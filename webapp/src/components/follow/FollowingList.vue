<template>
  <div class="following-list">
    <div class="text-h6 q-mb-md">
      <q-icon class="q-mr-sm" name="person_add" />
      {{ $t('following') }}
      <q-badge v-if="totalFollowing > 0" class="q-ml-sm" color="primary">
        {{ totalFollowing }}
      </q-badge>
    </div>

    <div v-if="followStore.isLoading && following.length === 0" class="text-center q-py-lg">
      <q-spinner color="primary" size="3em" />
      <div class="text-grey q-mt-md">{{ $t('loading') }}</div>
    </div>

    <div v-else-if="following.length === 0" class="text-center q-py-xl text-grey">
      <q-icon class="q-mb-md" name="person_add_alt" size="4em" />
      <div class="text-h6">{{ $t('no-following') }}</div>
      <div class="text-body2 q-mt-sm">{{ $t('no-following-description', 'Este usuário ainda não segue ninguém') }}</div>
    </div>

    <div v-else>
      <q-list separator>
        <q-item v-for="user in following" :key="user.id" class="following-item" clickable @click="$router.push(`/${user.username}`)">
          <q-item-section avatar>
            <q-avatar size="48px">
              <img v-if="user.avatar" :alt="user.display_name" :src="user.avatar" />
              <q-icon v-else name="person" size="24px" />
            </q-avatar>
          </q-item-section>

          <q-item-section>
            <q-item-label class="text-weight-medium">
              {{ user.display_name }}
            </q-item-label>
            <q-item-label caption>@{{ user.username }}</q-item-label>
          </q-item-section>

          <q-item-section side>
            <FollowButton dense size="sm" :user-id="user.id" />
          </q-item-section>
        </q-item>
      </q-list>

      <!-- Load More Button -->
      <div v-if="hasMore" class="text-center q-mt-lg">
        <q-btn color="primary" flat icon="expand_more" :label="$t('load-more')" :loading="followStore.isLoading" @click="loadMore" />
      </div>

      <!-- Loading More Indicator -->
      <div v-if="followStore.isLoading && following.length > 0" class="text-center q-py-md">
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

const following = computed(() => followStore.following)
const totalFollowing = computed(() => followStore.followingPagination.total)
const hasMore = computed(() => followStore.followingPagination.has_more)

onMounted(async () => {
  await loadFollowing(1)
})

onUnmounted(() => {
  followStore.clearFollowing()
})

async function loadFollowing(page: number = 1) {
  try {
    await followStore.getFollowing(props.userId, page)
  } catch (error) {
    console.error('Error loading following:', error)
  }
}

async function loadMore() {
  const nextPage = followStore.followingPagination.current_page + 1
  await loadFollowing(nextPage)
}
</script>

<style scoped>
.following-list {
  max-width: 600px;
  margin: 0 auto;
}

.following-item {
  border-radius: 8px;
  margin-bottom: 4px;
  transition: background-color 0.2s ease;
}

.following-item:hover {
  background-color: rgba(0, 0, 0, 0.04);
}
</style>
