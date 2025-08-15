<template>
  <q-btn dense flat icon="menu" @click="shelfMenu = true" />

  <q-dialog v-model="shelfMenu" position="right">
    <q-card>
      <q-card-section class="column q-gutter-y-sm q-pb-none text-center">
        <!-- User Info Section -->
        <div v-if="currentUser" class="q-mb-md">
          <!-- Avatar -->
          <q-avatar class="q-mb-sm" size="60px">
            <img v-if="currentUser.avatar" :alt="currentUser.display_name" :src="currentUser.avatar" />
            <q-icon v-else name="person" size="30px" />
          </q-avatar>

          <!-- User Name -->
          <div class="text-h6 text-weight-medium">{{ currentUser.display_name }}</div>
          <div class="text-body2 text-grey q-mb-sm">@{{ currentUser.username }}</div>

          <!-- Stats Row -->
          <div class="row q-gutter-md justify-center q-mb-sm">
            <!-- Books -->
            <div class="text-center">
              <div class="text-weight-bold">{{ currentUser.books?.length || 0 }}</div>
              <div class="text-caption text-grey">{{ $t('books', { count: currentUser.books?.length || 0 }) }}</div>
            </div>
            <!-- Followers -->
            <div class="text-center">
              <div class="text-weight-bold">{{ currentUser.followers_count }}</div>
              <div class="text-caption text-grey">{{ $t('follower', { count: currentUser.followers_count }) }}</div>
            </div>
            <!-- Following -->
            <div class="text-center">
              <div class="text-weight-bold">{{ currentUser.following_count }}</div>
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
import { useAuthStore, useFollowStore, usePeopleStore } from '@/stores'
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
const router = useRouter()

const authStore = useAuthStore()
const followStore = useFollowStore()
const peopleStore = usePeopleStore()

const bookLabels = {
  authors: t('order-by-author'),
  addedIn: t('order-by-date'),
  readIn: t('order-by-read'),
  title: t('order-by-title')
}
const shelfMenu = ref(false)

const currentUser = computed(() => {
  if (router.currentRoute.value.path !== '/' && peopleStore.person?.id) {
    return peopleStore.person
  }
  return authStore.user
})

const sortKeyComputed = computed(() => props.sortKey)
const ascDescComputed = computed(() => props.ascDesc)
const isAbleToFollow = computed(() => {
  return router.currentRoute.value.path !== '/' && authStore.isAuthenticated && peopleStore.person.id !== authStore.user.id
})
const isFollowingPerson = computed(() => {
  if (!peopleStore.person?.id) return false
  return followStore.isFollowing(peopleStore.person.id)
})

onMounted(async () => {
  // Se estivermos na própria estante, atualiza os dados do usuário
  if (router.currentRoute.value.path === '/') {
    await authStore.refreshUser()
  }
})

async function followOrUnfollow() {
  if (!peopleStore.person?.id) return

  if (isFollowingPerson.value) {
    await peopleStore.unfollow(peopleStore.person.id)
  } else {
    await peopleStore.follow(peopleStore.person.id)
  }

  // Atualiza os dados do usuário para refletir as novas contagens
  await authStore.refreshUser()
}
</script>
