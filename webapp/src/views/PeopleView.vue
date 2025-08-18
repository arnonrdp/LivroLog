<template>
  <q-page padding>
    <q-input v-model="filter" color="primary" debounce="300" dense flat :label="$t('search-for-people')">
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
    </q-input>
    <q-table
      v-model:pagination="pagination"
      card-container-class="justify-center"
      class="q-mt-md bg-transparent"
      :columns="columns"
      :filter="filter"
      grid
      :no-results-label="$t('no-one-found')"
      row-key="id"
      :rows="userStore.people"
      @request="getUsers"
    >
      <template v-slot:item="props">
        <div class="q-pa-sm">
          <q-card class="full-height people-card">
            <router-link class="card-link" :to="props.row.username">
              <q-card-section class="text-center">
                <q-avatar class="bg-transparent" size="100px">
                  <q-img v-if="props.row.avatar" alt="avatar" :src="props.row.avatar" />
                  <q-icon v-else name="person" size="60px" />
                </q-avatar>
              </q-card-section>
              <q-card-section class="flex flex-center q-pt-none text-center">
                <div>
                  <div class="text-weight-medium">
                    {{ props.row.display_name || props.row.name || props.row.username }}
                  </div>
                  <div class="text-body2 text-grey">@{{ props.row.username }}</div>
                </div>
              </q-card-section>
            </router-link>

            <!-- Follow Stats -->
            <q-card-section v-if="props.row.followers_count !== undefined" class="text-center q-pt-none">
              <div class="row justify-center q-gutter-sm text-body2 text-grey">
                <div>
                  <strong>{{ props.row.followers_count || 0 }}</strong>
                  {{ $t('followers') }}
                </div>
                <div>
                  <strong>{{ props.row.following_count || 0 }}</strong>
                  {{ $t('following') }}
                </div>
              </div>
            </q-card-section>

            <!-- Follow Button -->
            <q-card-actions class="justify-center">
              <q-btn
                v-if="!isSelf(props.row.id)"
                class="follow-btn"
                :color="getFollowingStatus(props.row.id, props.row.is_following) ? 'grey-7' : 'primary'"
                dense
                :icon="getFollowingStatus(props.row.id, props.row.is_following) ? 'person_remove' : 'person_add'"
                :label="getFollowingStatus(props.row.id, props.row.is_following) ? $t('unfollow') : $t('follow')"
                :loading="userLoadingStates[props.row.id] || false"
                :outline="getFollowingStatus(props.row.id, props.row.is_following)"
                :unelevated="!getFollowingStatus(props.row.id, props.row.is_following)"
                @click.stop="toggleFollow(props.row.id, props.row.is_following)"
              >
                <q-tooltip v-if="followStore.isMutualFollow(props.row.id)">
                  {{ $t('mutual-follow') }}
                </q-tooltip>
                <q-tooltip v-else-if="followStore.isFollowedBy(props.row.id)">
                  {{ $t('follows-you') }}
                </q-tooltip>
              </q-btn>
            </q-card-actions>
          </q-card>
        </div>
      </template>
    </q-table>
  </q-page>
</template>

<script setup lang="ts">
import type { User } from '@/models'
import { useFollowStore, useUserStore } from '@/stores'
import type { QTableColumn, QTableProps } from 'quasar'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
document.title = `LivroLog | ${t('people')}`

const followStore = useFollowStore()
const userStore = useUserStore()

const localFollowStatus = ref<Record<string, boolean>>({})
const userLoadingStates = ref<Record<string, boolean>>({})

const columns: QTableColumn<User>[] = [
  { name: 'id', label: 'ID', field: 'id' },
  { name: 'display_name', label: 'Display Name', field: 'display_name', align: 'left' },
  { name: 'username', label: 'Username', field: 'username' }
]
const filter = ref('')
const pagination = ref<NonNullable<QTableProps['pagination']>>({ descending: true, page: 1, rowsNumber: 0, rowsPerPage: 20 })

onMounted(() => {
  getUsers({ pagination: pagination.value })
})

function isSelf(userId: string): boolean {
  return userStore.me.id === userId
}

function getFollowingStatus(userId: string, apiStatus?: boolean): boolean {
  // Priority: local state > API status > store state
  if (localFollowStatus.value[userId] !== undefined) {
    return localFollowStatus.value[userId]
  }
  if (apiStatus !== undefined) {
    return apiStatus
  }
  return followStore.isFollowing(userId)
}

async function toggleFollow(userId: string, currentStatus?: boolean) {
  if (userLoadingStates.value[userId]) return

  const wasFollowing = getFollowingStatus(userId, currentStatus)

  // Set loading state for this specific user
  userLoadingStates.value[userId] = true

  // Optimistic update
  localFollowStatus.value[userId] = !wasFollowing

  try {
    if (wasFollowing) {
      await followStore.deleteUserFollow(userId)
    } else {
      await followStore.postUserFollow(userId)
    }
    // Keep the local state updated with the successful result
    localFollowStatus.value[userId] = !wasFollowing
  } catch (error) {
    // Revert on error
    localFollowStatus.value[userId] = wasFollowing
    console.error('Error toggling follow:', error)
  } finally {
    // Clear loading state for this specific user
    userLoadingStates.value[userId] = false
  }
}

async function getUsers(props: Partial<QTableProps>) {
  if (props.pagination) {
    pagination.value = props.pagination
  }

  const params = {
    filter: filter.value || undefined,
    pagination: pagination.value
  }

  await userStore.getUsers(params).then(() => (pagination.value.rowsNumber = userStore.meta.total))
}
</script>

<style scoped>
.q-input {
  margin: 0 auto;
  max-width: 32rem;
}

.people-card {
  width: 200px;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.people-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.card-link {
  color: rgba(0, 0, 0, 0.85);
  text-decoration: none;
  display: block;
}

.card-link:hover {
  color: rgba(0, 0, 0, 0.85);
}

.follow-btn {
  min-width: 100px;
}
</style>
