<template>
  <q-btn dense flat icon="menu" @click="shelfMenu = true" />

  <q-dialog v-model="shelfMenu" position="right">
    <q-card>
      <q-card-section class="column q-gutter-y-sm q-pb-none text-center">
        <b class="text-lower">{{ `${$t('friends.following')} ${peopleStore.getPerson.following?.length || 0}` }}</b>
        <q-btn
          v-if="isAbleToFollow"
          class="q-ml-sm text-bold"
          flat
          :icon-right="userStore.isFollowing ? 'person_remove' : 'person_add'"
          :label="$t('friends.follower', { count: peopleStore.getPerson.followers?.length || 0 })"
          no-caps
          @click="followOrUnfollow"
        >
          <q-tooltip>{{ userStore.isFollowing ? $t('friends.unfollow') : $t('friends.follow') }}</q-tooltip>
        </q-btn>
        <b v-else>{{ $t('friends.follower', { count: peopleStore.getPerson.followers?.length || 0 }) }}</b>
      </q-card-section>
      <q-card-section>
        <q-input
          dense
          debounce="300"
          outlined
          :placeholder="$t('book.search')"
          :model-value="modelValue"
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
          <q-item clickable v-for="(label, value) in bookLabels" :key="label" @click="$emit('sort', value)">
            <q-item-section>{{ label }}</q-item-section>
            <q-item-section avatar>
              <q-icon v-if="value === sortKey" size="xs" :name="ascDesc === 'asc' ? 'arrow_downward' : 'arrow_upward'" />
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { usePeopleStore, useUserStore } from '@/store'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

defineEmits(['sort', 'update:model-value'])
defineProps<{
  modelValue: string
}>()

const { t } = useI18n()
const peopleStore = usePeopleStore()
const userStore = useUserStore()

const ascDesc = ref('asc')
const bookLabels = ref({
  authors: t('book.order-by-author'),
  addedIn: t('book.order-by-date'),
  readIn: t('book.order-by-read'),
  title: t('book.order-by-title')
})
const router = useRouter()
const shelfMenu = ref(false)
const sortKey = ref<string | number>('')

const isAbleToFollow = computed(() => {
  return router.currentRoute.value.path !== '/' && userStore.isAuthenticated && peopleStore.getPerson.uid !== userStore.getUser.uid
})

function followOrUnfollow() {
  if (userStore.isFollowing) {
    peopleStore.removeFollower(peopleStore.getPerson.uid)
  } else {
    peopleStore.addFollower(peopleStore.getPerson.uid)
  }
}
</script>
