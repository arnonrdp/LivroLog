<template>
  <q-btn dense flat icon="menu" @click="shelfMenu = true" />

  <q-dialog v-model="shelfMenu" position="right">
    <q-card>
      <q-card-section class="column q-gutter-y-sm q-pb-none text-center">
        <!-- User Info Section -->
        <div v-if="user" class="q-mb-md">
          <!-- Avatar -->
          <q-avatar class="q-mb-sm" size="60px">
            <img v-if="user.avatar" :alt="user.display_name" :src="user.avatar" />
            <q-icon v-else name="person" size="30px" />
          </q-avatar>

          <!-- User Name -->
          <div class="text-h6 text-weight-medium">{{ user.display_name }}</div>
          <div class="text-body2 text-grey q-mb-sm">@{{ user.username }}</div>

          <!-- Stats Row -->
          <div class="row q-gutter-md justify-center q-mb-sm">
            <!-- Books -->
            <div class="text-center">
              <div class="text-weight-bold">{{ user.books?.length || 0 }}</div>
              <div class="text-caption text-grey">{{ $t('books', { count: user.books?.length || 0 }) }}</div>
            </div>
            <!-- Followers -->
            <div class="text-center">
              <div class="text-weight-bold">{{ user.followers_count }}</div>
              <div class="text-caption text-grey">{{ $t('follower', { count: user.followers_count }) }}</div>
            </div>
            <!-- Following -->
            <div class="text-center">
              <div class="text-weight-bold">{{ user.following_count }}</div>
              <div class="text-caption text-grey">{{ $t('following') }}</div>
            </div>
          </div>

          <!-- Follow Button -->
          <q-btn
            v-if="isAbleToFollow"
            class="full-width q-mb-sm"
            :color="isFollowingPerson ? 'grey' : 'primary'"
            :icon="isFollowingPerson ? 'person_remove' : 'person_add'"
            :label="isFollowingPerson ? $t('unfollow') : $t('follow')"
            no-caps
            @click="followOrUnfollow"
          />
        </div>
      </q-card-section>
      <q-card-section>
        <q-input
          debounce="300"
          dense
          :model-value="modelValue"
          outlined
          :placeholder="$t('search')"
          @update:model-value="$emit('update:model-value', $event)"
        >
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </q-card-section>
      <q-card-section>
        {{ $t('sort') }}:
        <q-list bordered class="non-selectable">
          <q-item v-for="(label, value) in bookLabels" :key="label" clickable @click="$emit('sort', value)">
            <q-item-section>{{ label }}</q-item-section>
            <q-item-section avatar>
              <q-icon v-if="value === sortKeyComputed" :name="ascDescComputed === 'asc' ? 'arrow_downward' : 'arrow_upward'" size="xs" />
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { useAuthStore, useFollowStore, useUserStore } from '@/stores'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

defineEmits(['sort', 'update:model-value'])
const props = defineProps<{
  modelValue: string
  sortKey?: string | number
  ascDesc?: string
}>()

const { t } = useI18n()
const router = useRouter()

const authStore = useAuthStore()
const followStore = useFollowStore()
const userStore = useUserStore()

const bookLabels = {
  authors: t('order-by-author'),
  addedIn: t('order-by-date'),
  readIn: t('order-by-read'),
  title: t('order-by-title')
}
const shelfMenu = ref(false)

const user = computed(() => {
  // When we're on the home page, show logged-in user
  if (router.currentRoute.value.path === '/') {
    return userStore.me
  }
  // When on other user's page, show that user (from userStore.user)
  return userStore.user
})

const sortKeyComputed = computed(() => props.sortKey)
const ascDescComputed = computed(() => props.ascDesc)
const isAbleToFollow = computed(() => {
  return router.currentRoute.value.path !== '/' && authStore.isAuthenticated && userStore.user.id !== userStore.me.id
})
const isFollowingPerson = computed(() => {
  if (!userStore.user?.id) return false
  return followStore.isFollowing(userStore.user.id)
})

async function followOrUnfollow() {
  if (!userStore.user?.id) return

  if (isFollowingPerson.value) {
    await followStore.deleteUserFollow(userStore.user.id)
  } else {
    await followStore.postUserFollow(userStore.user.id)
  }

  await authStore.refreshUser()
}
</script>
