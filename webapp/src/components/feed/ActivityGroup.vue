<template>
  <q-card :class="['activity-group', 'q-mb-md', { 'activity-highlight': isHighlighted }]">
    <q-card-section class="row items-center q-pb-none">
      <router-link class="user-link row items-center" :to="`/${group.user.username}`">
        <q-avatar class="q-mr-sm" size="40px">
          <q-img v-if="group.user.avatar" :src="group.user.avatar" />
          <q-icon v-else name="person" size="24px" />
        </q-avatar>
        <div>
          <div class="text-weight-medium">{{ group.user.display_name }}</div>
          <div class="text-caption text-grey">@{{ group.user.username }}</div>
        </div>
      </router-link>
      <q-space />
      <div class="text-caption text-grey">{{ formatDate(group.date) }}</div>
    </q-card-section>

    <q-card-section>
      <div class="activity-description">
        <q-icon class="q-mr-sm" :color="activityColor" :name="activityIcon" size="20px" />
        <span>{{ activityText }}</span>
      </div>

      <!-- Books grid for book activities -->
      <div v-if="isBookActivity" class="books-grid q-mt-md">
        <router-link v-for="activity in group.activities" :key="activity.id" class="book-item" :to="`/books/${activity.subject?.id}`">
          <q-img
            v-if="activity.subject?.type === 'Book' && activity.subject.thumbnail"
            :alt="activity.subject.title"
            class="book-cover"
            fit="cover"
            :src="activity.subject.thumbnail"
          />
          <div v-else class="book-cover book-placeholder">
            <q-icon color="grey-5" name="menu_book" size="32px" />
          </div>
          <q-tooltip>{{ activity.subject?.type === 'Book' ? activity.subject.title : '' }}</q-tooltip>
        </router-link>
      </div>

      <!-- Review activity -->
      <div v-else-if="group.type === 'review_written'" class="q-mt-md">
        <router-link
          v-for="activity in group.activities"
          :key="activity.id"
          class="review-item row items-center"
          :to="`/books/${activity.subject?.type === 'Review' ? activity.subject.book.id : ''}`"
        >
          <q-img
            v-if="activity.subject?.type === 'Review' && activity.subject.book.thumbnail"
            class="book-cover-small q-mr-md"
            fit="cover"
            :src="activity.subject.book.thumbnail"
          />
          <div>
            <div class="text-weight-medium">
              {{ activity.subject?.type === 'Review' ? activity.subject.book.title : '' }}
            </div>
            <q-rating
              v-if="activity.subject?.type === 'Review'"
              color="amber"
              icon="star"
              :model-value="activity.subject.rating"
              readonly
              size="16px"
            />
          </div>
        </router-link>
      </div>

      <!-- Follow activity -->
      <div v-else-if="group.type === 'user_followed'" class="q-mt-md">
        <router-link
          v-for="activity in group.activities"
          :key="activity.id"
          class="followed-user row items-center"
          :to="`/${activity.subject?.type === 'User' ? activity.subject.username : ''}`"
        >
          <q-avatar class="q-mr-sm" size="36px">
            <q-img v-if="activity.subject?.type === 'User' && activity.subject.avatar" :src="activity.subject.avatar" />
            <q-icon v-else name="person" size="20px" />
          </q-avatar>
          <div>
            <div class="text-weight-medium">
              {{ activity.subject?.type === 'User' ? activity.subject.display_name : '' }}
            </div>
            <div class="text-caption text-grey">@{{ activity.subject?.type === 'User' ? activity.subject.username : '' }}</div>
          </div>
        </router-link>
      </div>

      <!-- Actions (Like and Comment) -->
      <ActivityGroupActions :group="group" @toggle-comments="toggleComments" />

      <!-- Comments Section -->
      <q-slide-transition>
        <ActivityComments v-if="showComments" :activity-id="group.first_activity_id" />
      </q-slide-transition>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import type { ActivityGroup } from '@/models'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ActivityComments from './ActivityComments.vue'
import ActivityGroupActions from './ActivityGroupActions.vue'

const props = defineProps<{
  group: ActivityGroup
  initialShowComments?: boolean
  isHighlighted?: boolean
}>()

const emit = defineEmits<{
  commentsOpened: [activityId: string]
}>()

const showComments = ref(false)

onMounted(() => {
  if (props.initialShowComments) {
    showComments.value = true
    emit('commentsOpened', props.group.first_activity_id)
  }
})

// Watch for prop changes (when navigating from notification while already on feed)
watch(
  () => props.initialShowComments,
  (newValue) => {
    if (newValue && !showComments.value) {
      showComments.value = true
      emit('commentsOpened', props.group.first_activity_id)
    }
  }
)

function toggleComments() {
  showComments.value = !showComments.value
  if (showComments.value) {
    emit('commentsOpened', props.group.first_activity_id)
  }
}

const { t, d } = useI18n()

const isBookActivity = computed(() => ['book_added', 'book_started', 'book_read'].includes(props.group.type))

const activityIcon = computed(() => {
  switch (props.group.type) {
    case 'book_added':
      return 'add_circle'
    case 'book_started':
      return 'auto_stories'
    case 'book_read':
      return 'check_circle'
    case 'review_written':
      return 'rate_review'
    case 'user_followed':
      return 'person_add'
    default:
      return 'info'
  }
})

const activityColor = computed(() => {
  switch (props.group.type) {
    case 'book_added':
      return 'primary'
    case 'book_started':
      return 'orange'
    case 'book_read':
      return 'positive'
    case 'review_written':
      return 'amber-8'
    case 'user_followed':
      return 'info'
    default:
      return 'grey'
  }
})

const activityText = computed(() => {
  const count = props.group.count
  switch (props.group.type) {
    case 'book_added':
      return t('feed.added-books', count)
    case 'book_started':
      return t('feed.started-books', count)
    case 'book_read':
      return t('feed.read-books', count)
    case 'review_written':
      return t('feed.wrote-reviews', count)
    case 'user_followed':
      return t('feed.followed-users', count)
    default:
      return ''
  }
})

function formatDate(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diffDays = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24))

  if (diffDays === 0) return t('feed.today')
  if (diffDays === 1) return t('feed.yesterday')
  if (diffDays < 7) return t('feed.days-ago', diffDays)

  return d(date, 'short')
}
</script>

<style scoped lang="sass">
.activity-group
  max-width: 600px
  margin: 0 auto 16px auto
  transition: box-shadow 0.3s ease, background-color 0.3s ease

// Animation defined globally in main.sass

.user-link
  text-decoration: none
  color: inherit
  &:hover
    opacity: 0.8

.activity-description
  display: flex
  align-items: center
  color: var(--q-dark)

.books-grid
  display: grid
  grid-template-columns: repeat(auto-fill, minmax(60px, 1fr))
  gap: 8px
  max-width: 100%

.book-item
  text-decoration: none

.book-cover
  width: 60px
  height: 90px
  border-radius: 4px
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1)
  transition: transform 0.2s ease
  &:hover
    transform: scale(1.05)

.book-placeholder
  display: flex
  align-items: center
  justify-content: center
  background: #f5f5f5

.book-cover-small
  width: 50px
  height: 75px
  border-radius: 4px

.review-item, .followed-user
  text-decoration: none
  color: inherit
  padding: 8px
  border-radius: 8px
  transition: background 0.2s ease
  &:hover
    background: rgba(0, 0, 0, 0.03)
</style>
