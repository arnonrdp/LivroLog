<template>
  <div class="activity-comments q-mt-md">
    <!-- Loading -->
    <div v-if="isLoading" class="text-center q-pa-md">
      <q-spinner color="primary" size="24px" />
    </div>

    <!-- Comments List -->
    <div v-else-if="comments.length > 0" class="comments-list">
      <div v-for="comment in comments" :key="comment.id" class="comment-item q-mb-sm">
        <div class="row items-start">
          <q-avatar class="q-mr-sm" size="28px">
            <q-img v-if="comment.user.avatar" :src="comment.user.avatar" />
            <q-icon v-else name="person" size="16px" />
          </q-avatar>
          <div class="col">
            <div class="row items-center">
              <router-link class="text-weight-medium text-dark comment-username" :to="`/${comment.user.username}`">
                {{ comment.user.display_name }}
              </router-link>
              <span class="text-caption text-grey q-ml-sm">
                {{ formatDate(comment.created_at) }}
              </span>
              <q-space />
              <q-btn
                v-if="comment.is_owner"
                color="grey"
                flat
                icon="delete"
                round
                size="xs"
                @click="handleDeleteComment(comment.id)"
              />
            </div>
            <p class="q-mb-none text-body2">{{ comment.content }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center text-grey q-pa-sm">
      {{ $t('feed.no-comments') }}
    </div>

    <!-- Add Comment Form -->
    <div class="add-comment row items-center q-mt-sm">
      <q-input
        v-model="newComment"
        class="col"
        dense
        :label="$t('feed.add-comment')"
        maxlength="1000"
        outlined
        @keyup.enter="submitComment"
      />
      <q-btn :disable="!newComment.trim() || isSubmitting" color="primary" flat icon="send" round @click="submitComment">
        <q-spinner v-if="isSubmitting" color="primary" size="16px" />
      </q-btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Comment } from '@/models'
import { useActivityStore } from '@/stores'
import type { Locale } from 'date-fns'
import { formatDistanceToNow } from 'date-fns'
import { enUS, ja, pt, tr } from 'date-fns/locale'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  activityId: string
}>()

const { locale } = useI18n()
const activityStore = useActivityStore()

const comments = ref<Comment[]>([])
const newComment = ref('')
const isLoading = ref(false)
const isSubmitting = ref(false)

const localeMap: Record<string, Locale> = {
  en: enUS,
  pt: pt,
  ja: ja,
  tr: tr
}

onMounted(() => {
  loadComments()
})

function loadComments() {
  isLoading.value = true
  activityStore
    .getActivityComments(props.activityId)
    .then((data) => {
      comments.value = data
    })
    .finally(() => {
      isLoading.value = false
    })
}

function submitComment() {
  if (!newComment.value.trim() || isSubmitting.value) return

  isSubmitting.value = true
  activityStore
    .postActivityComment(props.activityId, newComment.value.trim())
    .then((comment) => {
      if (comment) {
        comments.value.push(comment)
        newComment.value = ''
      }
    })
    .finally(() => {
      isSubmitting.value = false
    })
}

function handleDeleteComment(commentId: string) {
  activityStore.deleteComment(commentId, props.activityId).then((success) => {
    if (success) {
      comments.value = comments.value.filter((c) => c.id !== commentId)
    }
  })
}

function formatDate(dateStr: string): string {
  const dateLocale = localeMap[locale.value] || enUS
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: dateLocale })
}
</script>

<style scoped lang="sass">
.comment-item
  padding: 8px
  background: rgba(0, 0, 0, 0.02)
  border-radius: 8px

.comment-username
  text-decoration: none
  &:hover
    text-decoration: underline
</style>
