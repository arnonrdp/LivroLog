<template>
  <div class="activity-actions row items-center q-mt-sm">
    <!-- Like Button -->
    <q-btn :color="group.is_liked ? 'red' : 'grey'" flat :icon="group.is_liked ? 'favorite' : 'favorite_border'" round size="sm" @click="toggleLike">
      <q-tooltip>{{ group.is_liked ? $t('feed.unlike') : $t('feed.like') }}</q-tooltip>
    </q-btn>
    <span v-if="group.likes_count > 0" class="text-caption text-grey q-ml-xs">
      {{ group.likes_count }}
    </span>

    <!-- Comment Button -->
    <q-btn class="q-ml-md" color="grey" flat icon="chat_bubble_outline" round size="sm" @click="toggleComments">
      <q-tooltip>{{ $t('feed.comments') }}</q-tooltip>
    </q-btn>
    <span v-if="group.comments_count > 0" class="text-caption text-grey q-ml-xs">
      {{ group.comments_count }}
    </span>
  </div>
</template>

<script setup lang="ts">
import type { ActivityGroup } from '@/models'
import { useActivityStore } from '@/stores'

const props = defineProps<{
  group: ActivityGroup
}>()

const emit = defineEmits<{
  toggleComments: []
}>()

const activityStore = useActivityStore()

function toggleLike() {
  const activityId = props.group.first_activity_id
  if (!activityId) return

  if (props.group.is_liked) {
    activityStore.deleteActivityLike(activityId)
  } else {
    activityStore.postActivityLike(activityId)
  }
}

function toggleComments() {
  emit('toggleComments')
}
</script>

<style scoped lang="sass">
.activity-actions
  padding: 4px 0
</style>
