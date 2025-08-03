<template>
  <q-btn dense flat icon="menu" @click="shelfMenu = true" />

  <q-dialog v-model="shelfMenu" position="right">
    <q-card>
      <q-card-section class="column q-gutter-y-sm q-pb-none text-center">
        <b class="text-lower">{{ `${$t('friends.following')} ${followingCount}` }}</b>
        <q-btn
          v-if="isAbleToFollow"
          class="q-ml-sm text-bold"
          flat
          :icon-right="isFollowingPerson ? 'person_remove' : 'person_add'"
          :label="$t('friends.follower', { count: followersCount })"
          no-caps
          @click="followOrUnfollow"
        >
          <q-tooltip>{{ isFollowingPerson ? $t('friends.unfollow') : $t('friends.follow') }}</q-tooltip>
        </q-btn>
        <b v-else>{{ $t('friends.follower', { count: followersCount }) }}</b>
      </q-card-section>
      <q-card-section>
        <q-input
          debounce="300"
          dense
          :model-value="modelValue"
          outlined
          :placeholder="$t('book.search')"
          @update:model-value="$emit('update:model-value', $event)"
        >
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </q-card-section>
      <q-card-section>
        {{ $t('book.sort') }}:
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
import { useAuthStore, usePeopleStore, useUserStore } from '@/stores'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

defineEmits(['sort', 'update:model-value'])
const props = defineProps<{
  modelValue: string
  sortKey?: string | number
  ascDesc?: string
}>()

const { t } = useI18n()
const authStore = useAuthStore()
const peopleStore = usePeopleStore()
const userStore = useUserStore()

const bookLabels = {
  authors: t('book.order-by-author'),
  addedIn: t('book.order-by-date'),
  readIn: t('book.order-by-read'),
  title: t('book.order-by-title')
}
const followersCount = ref(0)
const followingCount = ref(0)
const router = useRouter()
const shelfMenu = ref(false)

const sortKeyComputed = computed(() => props.sortKey)
const ascDescComputed = computed(() => props.ascDesc)
const isAbleToFollow = computed(() => {
  return router.currentRoute.value.path !== '/' && authStore.isAuthenticated && peopleStore.person.id !== authStore.user.id
})
const isFollowingPerson = computed(() => {
  if (!peopleStore.person?.id) return false
  // return userStore.isFollowing(peopleStore.person.id)
})

onMounted(() => {
  const user = router.currentRoute.value.path === '/' ? authStore.user : peopleStore.person
  followersCount.value = user.followers?.length || 0
  followingCount.value = user.following?.length || 0
})

function followOrUnfollow() {
  if (isFollowingPerson.value) {
    peopleStore.unfollow()
  } else {
    peopleStore.follow()
  }
}
</script>
