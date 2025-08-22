<template>
  <q-btn v-if="pendingRequestsCount > 0" flat icon="group_add" round @click="loadPendingRequests">
    <q-badge color="red" floating rounded>
      {{ pendingRequestsCount }}
    </q-badge>

    <q-menu fit :offset="[0, 8]">
      <q-list style="min-width: 20rem">
        <q-item-label header>{{ $t('follow-requests') }}</q-item-label>

        <q-item v-if="!pendingRequests.length" class="text-center text-grey">
          <q-item-section>{{ $t('no-pending-requests') }}</q-item-section>
        </q-item>

        <q-item v-for="request in pendingRequests" :key="request.id" class="q-px-md">
          <q-item-section avatar>
            <q-avatar size="40px">
              <img v-if="request.follower.avatar" :alt="request.follower.display_name" :src="request.follower.avatar" />
              <q-icon v-else name="person" size="20px" />
            </q-avatar>
          </q-item-section>

          <q-item-section>
            <q-item-label>{{ request.follower.display_name }}</q-item-label>
            <q-item-label caption>@{{ request.follower.username }}</q-item-label>
          </q-item-section>

          <q-item-section side>
            <div class="row q-gutter-xs">
              <q-btn color="positive" dense icon="check" outline round size="sm" @click="acceptRequest(request.id)" />
              <q-btn color="negative" dense icon="close" outline round size="sm" @click="rejectRequest(request.id)" />
            </div>
          </q-item-section>
        </q-item>
      </q-list>
    </q-menu>
  </q-btn>
</template>

<script setup lang="ts">
import type { FollowRequest } from '@/models/User'
import { useAuthStore, useFollowStore, useUserStore } from '@/stores'
import { computed, ref } from 'vue'

const authStore = useAuthStore()
const followStore = useFollowStore()
const userStore = useUserStore()

const isLoading = ref(false)
const pendingRequests = ref<FollowRequest[]>([])

const pendingRequestsCount = computed(() => {
  return userStore.me?.pending_follow_requests_count || 0
})

async function loadPendingRequests() {
  isLoading.value = true
  try {
    pendingRequests.value = await followStore.getFollowRequests()
  } catch (error) {
    console.error('Error loading pending requests:', error)
  } finally {
    isLoading.value = false
  }
}

async function acceptRequest(followId: number) {
  await followStore.acceptFollowRequest(followId)
  await loadPendingRequests()
  // Refresh user data to update count
  await authStore.refreshUser()
}

async function rejectRequest(followId: number) {
  await followStore.rejectFollowRequest(followId)
  await loadPendingRequests()
  // Refresh user data to update count
  await authStore.refreshUser()
}
</script>
