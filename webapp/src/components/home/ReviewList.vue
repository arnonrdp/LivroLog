<template>
  <div v-if="reviews.length > 0" class="q-mt-lg">
    <div class="text-h6 q-mb-md">{{ $t('reviews') }}</div>

    <q-card v-for="review in reviews" :key="review.id" bordered class="q-mb-md" flat>
      <q-card-section>
        <div class="row items-center q-mb-sm">
          <q-avatar class="q-mr-sm" size="32px">
            <img v-if="review.user?.avatar" :src="review.user.avatar" />
            <q-icon v-else name="person" />
          </q-avatar>
          <div class="col">
            <div class="text-subtitle2">{{ review.user?.display_name }}</div>
            <div class="text-caption text-grey">{{ formatDate(review.created_at) }}</div>
          </div>
          <q-rating color="amber" :model-value="review.rating" readonly size="sm" />
        </div>

        <div v-if="review.title" class="text-subtitle1 q-mb-sm">
          {{ review.title }}
        </div>

        <div class="text-body2 q-mb-sm">
          <div v-if="review.is_spoiler && !showSpoiler[review.id] && review.user_id !== currentUserId" class="text-italic text-grey">
            <q-icon class="q-mr-xs" name="warning" />
            {{ $t('spoiler-warning') }}
            <q-btn class="q-ml-sm" dense flat :label="$t('show-spoiler')" size="sm" @click="showSpoiler[review.id] = true" />
          </div>
          <div v-else>
            {{ review.content }}
          </div>
        </div>

        <div class="row items-center justify-between">
          <div class="text-caption text-grey">{{ $t('visibility') }}: {{ $t(review.visibility_level) }}</div>
          <div class="row items-center">
            <q-btn
              v-if="review.visibility_level === 'public'"
              class="text-grey"
              dense
              flat
              icon="thumb_up"
              :label="review.helpful_count"
              size="sm"
              @click="markAsHelpful(review.id)"
            />
          </div>
        </div>
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import type { Review } from '@/models'
import { useAuthStore, useReviewStore } from '@/stores'
import { computed, ref } from 'vue'

defineProps<{
  reviews: Review[]
}>()

const authStore = useAuthStore()
const reviewStore = useReviewStore()

const showSpoiler = ref<Record<string, boolean>>({})
const currentUserId = computed(() => authStore.user?.id)

function formatDate(dateString: string) {
  const date = new Date(dateString)
  return date.toLocaleDateString()
}

async function markAsHelpful(reviewId: string) {
  await reviewStore.postReviewHelpful(reviewId)
}
</script>
