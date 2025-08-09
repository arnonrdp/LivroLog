<template>
  <q-dialog v-model="isOpen" persistent>
    <q-card class="follow-modal">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">
          {{ title }}
        </div>
        <q-space />
        <q-btn v-close-popup dense flat icon="close" round />
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-tabs v-model="activeTab" active-color="primary" align="justify" class="text-grey" dense indicator-color="primary">
          <q-tab :label="$t('followers') + ` (${followersPagination.total})`" name="followers" />
          <q-tab :label="$t('following') + ` (${followingPagination.total})`" name="following" />
        </q-tabs>

        <q-separator class="q-my-md" />

        <q-tab-panels v-model="activeTab" animated>
          <!-- Followers Tab -->
          <q-tab-panel class="q-pa-none" name="followers">
            <div v-if="followStore.isLoading && followers.length === 0" class="text-center q-py-lg">
              <q-spinner color="primary" size="3em" />
            </div>

            <div v-else-if="followers.length === 0" class="text-center q-py-lg text-grey">
              <q-icon class="q-mb-md" name="people_outline" size="3em" />
              <div>{{ $t('no-followers') }}</div>
            </div>

            <q-list v-else class="follow-list" separator>
              <q-item v-for="follower in followers" :key="follower.id" class="follow-item" clickable @click="goToProfile(follower.username)">
                <q-item-section avatar>
                  <q-avatar size="40px">
                    <img v-if="follower.avatar" :alt="follower.display_name" :src="follower.avatar" />
                    <q-icon v-else name="person" size="20px" />
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

            <div v-if="followersPagination.has_more" class="text-center q-mt-md">
              <q-btn color="primary" flat :label="$t('load-more')" :loading="followStore.isLoading" size="sm" @click="loadMoreFollowers" />
            </div>
          </q-tab-panel>

          <!-- Following Tab -->
          <q-tab-panel class="q-pa-none" name="following">
            <div v-if="followStore.isLoading && following.length === 0" class="text-center q-py-lg">
              <q-spinner color="primary" size="3em" />
            </div>

            <div v-else-if="following.length === 0" class="text-center q-py-lg text-grey">
              <q-icon class="q-mb-md" name="person_add_alt" size="3em" />
              <div>{{ $t('no-following') }}</div>
            </div>

            <q-list v-else class="follow-list" separator>
              <q-item v-for="user in following" :key="user.id" class="follow-item" clickable @click="goToProfile(user.username)">
                <q-item-section avatar>
                  <q-avatar size="40px">
                    <img v-if="user.avatar" :alt="user.display_name" :src="user.avatar" />
                    <q-icon v-else name="person" size="20px" />
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

            <div v-if="followingPagination.has_more" class="text-center q-mt-md">
              <q-btn color="primary" flat :label="$t('load-more')" :loading="followStore.isLoading" size="sm" @click="loadMoreFollowing" />
            </div>
          </q-tab-panel>
        </q-tab-panels>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { useFollowStore } from '@/stores'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import FollowButton from './FollowButton.vue'

interface Props {
  modelValue: boolean
  userId: string
  initialTab?: 'followers' | 'following'
}

const props = withDefaults(defineProps<Props>(), {
  initialTab: 'followers'
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const router = useRouter()
const followStore = useFollowStore()

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const activeTab = ref(props.initialTab)

const followers = computed(() => followStore.followers)
const following = computed(() => followStore.following)
const followersPagination = computed(() => followStore.followersPagination)
const followingPagination = computed(() => followStore.followingPagination)

const title = computed(() => {
  return activeTab.value === 'followers' ? 'Seguidores' : 'Seguindo'
})

// Watch for modal opening
watch(isOpen, async (newValue) => {
  if (newValue) {
    // Load initial data
    if (activeTab.value === 'followers') {
      await followStore.getFollowers(props.userId, 1)
    } else {
      await followStore.getFollowing(props.userId, 1)
    }
  } else {
    // Clear data when modal closes
    followStore.clearFollowers()
    followStore.clearFollowing()
  }
})

// Watch tab changes
watch(activeTab, async (newTab) => {
  if (!isOpen.value) return

  if (newTab === 'followers' && followers.value.length === 0) {
    await followStore.getFollowers(props.userId, 1)
  } else if (newTab === 'following' && following.value.length === 0) {
    await followStore.getFollowing(props.userId, 1)
  }
})

async function loadMoreFollowers() {
  const nextPage = followersPagination.value.current_page + 1
  await followStore.getFollowers(props.userId, nextPage)
}

async function loadMoreFollowing() {
  const nextPage = followingPagination.value.current_page + 1
  await followStore.getFollowing(props.userId, nextPage)
}

function goToProfile(username: string) {
  isOpen.value = false
  router.push(`/${username}`)
}
</script>

<style scoped>
.follow-modal {
  width: 100%;
  max-width: 500px;
  max-height: 80vh;
}

.follow-list {
  max-height: 400px;
  overflow-y: auto;
}

.follow-item {
  border-radius: 4px;
  transition: background-color 0.2s ease;
}

.follow-item:hover {
  background-color: rgba(0, 0, 0, 0.04);
}
</style>
