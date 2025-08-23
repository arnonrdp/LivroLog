<template>
  <q-dialog v-model="showDialog" persistent>
    <q-card class="q-dialog-plugin" style="max-width: 100%; max-height: 90vh; width: 500px">
      <!-- Header -->
      <q-card-section class="row items-center q-pb-sm">
        <div class="text-h6">{{ dialogTitle }}</div>
        <q-space />
        <q-btn v-close-popup dense flat icon="close" round />
      </q-card-section>

      <q-separator />

      <!-- Tabs -->
      <q-tabs v-model="activeTab" class="text-grey" dense>
        <q-tab :label="`${$t('followers')} (${user?.followers_count || 0})`" name="followers" />
        <q-tab :label="`${$t('following')} (${user?.following_count || 0})`" name="following" />
      </q-tabs>

      <q-separator />

      <!-- Tab Panels -->
      <q-tab-panels v-model="activeTab" style="height: 400px">
        <!-- Followers Panel -->
        <q-tab-panel class="q-pa-none" name="followers">
          <q-scroll-area style="height: 400px">
            <!-- Loading -->
            <div v-if="followStore._isLoading && !followers.length" class="text-center q-py-xl">
              <q-spinner color="primary" size="3em" />
              <div class="text-grey q-mt-md">{{ $t('loading') }}</div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!followers.length" class="text-center q-py-xl">
              <q-icon class="q-mb-md" color="grey" name="people_outline" size="4em" />
              <div class="text-grey">{{ $t('no-followers') }}</div>
            </div>

            <!-- Followers List -->
            <q-list v-else separator>
              <q-item v-for="follower in followers" :key="follower.id" class="q-px-md q-py-sm">
                <q-item-section avatar @click="$router.push(`/${follower.username}`)">
                  <q-avatar size="40px">
                    <img v-if="follower.avatar" :alt="follower.display_name" :src="follower.avatar" />
                    <q-icon v-else name="person" size="20px" />
                  </q-avatar>
                </q-item-section>

                <q-item-section @click="$router.push(`/${follower.username}`)">
                  <q-item-label>{{ follower.display_name }}</q-item-label>
                  <q-item-label caption>@{{ follower.username }}</q-item-label>
                </q-item-section>

                <q-item-section side>
                  <q-btn
                    v-if="canFollowUser(follower.id)"
                    :color="follower.is_following ? 'grey' : 'primary'"
                    dense
                    :disable="loadingUsers.has(follower.id)"
                    :icon="follower.is_following ? 'person_remove' : 'person_add'"
                    :label="follower.is_following ? $t('unfollow') : $t('follow')"
                    :loading="loadingUsers.has(follower.id)"
                    no-caps
                    outline
                    size="sm"
                    @click="toggleFollow(follower)"
                  />
                </q-item-section>
              </q-item>
            </q-list>
          </q-scroll-area>
        </q-tab-panel>

        <!-- Following List -->
        <q-tab-panel class="q-pa-none" name="following">
          <q-scroll-area style="height: 400px">
            <!-- Loading -->
            <div v-if="followStore._isLoading && !following.length" class="text-center q-py-xl">
              <q-spinner color="primary" size="3em" />
              <div class="text-grey q-mt-md">{{ $t('loading') }}</div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!following.length" class="text-center q-py-xl">
              <q-icon class="q-mb-md" color="grey" name="people_outline" size="4em" />
              <div class="text-grey">{{ $t('no-following') }}</div>
            </div>

            <!-- Following List -->
            <q-list v-else separator>
              <q-item v-for="followingUser in following" :key="followingUser.id" class="q-px-md q-py-sm">
                <q-item-section avatar @click="$router.push(`/${followingUser.username}`)">
                  <q-avatar size="40px">
                    <img v-if="followingUser.avatar" :alt="followingUser.display_name" :src="followingUser.avatar" />
                    <q-icon v-else name="person" size="20px" />
                  </q-avatar>
                </q-item-section>

                <q-item-section @click="$router.push(`/${followingUser.username}`)">
                  <q-item-label>
                    {{ followingUser.display_name }}
                  </q-item-label>
                  <q-item-label caption>@{{ followingUser.username }}</q-item-label>
                </q-item-section>

                <q-item-section>
                  <div class="text-right">
                    <q-chip v-if="followingUser.is_follower" color="grey-6" dense size="sm" text-color="white">{{ $t('follows-you') }}</q-chip>
                  </div>
                </q-item-section>

                <q-item-section side>
                  <q-btn
                    v-if="canFollowUser(followingUser.id)"
                    :color="followingUser.is_following ? 'grey' : 'primary'"
                    dense
                    :disable="loadingUsers.has(followingUser.id)"
                    :icon="followingUser.is_following ? 'person_remove' : 'person_add'"
                    :label="followingUser.is_following ? $t('unfollow') : $t('follow')"
                    :loading="loadingUsers.has(followingUser.id)"
                    no-caps
                    outline
                    size="sm"
                    @click="toggleFollow(followingUser)"
                  />
                </q-item-section>
              </q-item>
            </q-list>
          </q-scroll-area>
        </q-tab-panel>
      </q-tab-panels>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import { useAuthStore, useFollowStore, useUserStore } from '@/stores'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  user?: User
  initialTab?: 'followers' | 'following'
}

const props = withDefaults(defineProps<Props>(), {
  initialTab: 'followers'
})

const showDialog = defineModel<boolean>({ required: true })

const { t } = useI18n()

const authStore = useAuthStore()
const followStore = useFollowStore()
const userStore = useUserStore()

const activeTab = ref<'followers' | 'following'>(props.initialTab)
const followers = ref<User[]>([])
const following = ref<User[]>([])
const loadingUsers = ref<Set<string>>(new Set())

const dialogTitle = computed(() => {
  return props.user?.display_name || t('user-connections')
})

watch(
  () => props.user?.id,
  () => {
    followers.value = []
    following.value = []
  }
)

watch(
  () => props.initialTab,
  (newTab) => {
    activeTab.value = newTab
  },
  { immediate: true }
)

watch(
  [showDialog, activeTab],
  async ([isOpen, tab]) => {
    if (!isOpen || !props.user?.id || !isOwnProfile.value) return

    if (tab === 'followers' && !followers.value.length) {
      await loadFollowers()
    } else if (tab === 'following' && !following.value.length) {
      await loadFollowing()
    }
  },
  { immediate: true }
)

const canFollowUser = (userId: string): boolean => {
  return authStore.isAuthenticated && userStore.me?.id !== userId
}

const isOwnProfile = computed(() => {
  return userStore.me?.id === props.user?.id
})

function toggleFollow(user: User) {
  if (!user.id || loadingUsers.value.has(user.id)) return

  loadingUsers.value.add(user.id)

  const followAction = user.is_following ? followStore.deleteUserFollow(user.id) : followStore.postUserFollow(user.id)

  followAction
    .then(() => {
      const targetUser =
        activeTab.value === 'followers' ? followers.value.find((u) => u.id === user.id) : following.value.find((u) => u.id === user.id)

      if (targetUser) {
        targetUser.is_following = !user.is_following
      }

      if (activeTab.value === 'followers' && userStore.me && userStore.me.following_count !== undefined) {
        userStore.me.following_count += user.is_following ? -1 : 1
      }

      if (activeTab.value === 'following' && user.is_following) {
        followStore.removeFromFollowingList(props.user?.id || '', user.id)
        following.value = following.value.filter((u) => u.id !== user.id)
        followStore.clearFollowersData(props.user?.id)
      }
    })
    .finally(() => {
      loadingUsers.value.delete(user.id)
    })
}

async function loadFollowers() {
  if (!props.user?.id || !isOwnProfile.value) return

  const result = await followStore.getUserFollowers(props.user.id)
  if (result) {
    followers.value = result
  }
}

async function loadFollowing() {
  if (!props.user?.id || !isOwnProfile.value) return

  const result = await followStore.getUserFollowing(props.user.id)
  if (result) {
    following.value = result
  }
}
</script>
