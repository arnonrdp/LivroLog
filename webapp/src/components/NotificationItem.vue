<template>
  <q-item
    :class="{ 'bg-blue-1': !notification.is_read }"
    clickable
    @click="handleClick"
  >
    <q-item-section avatar>
      <q-avatar size="40px">
        <q-img v-if="notification.actor.avatar" :src="notification.actor.avatar" />
        <q-icon v-else name="person" size="24px" />
      </q-avatar>
    </q-item-section>

    <q-item-section>
      <q-item-label>
        <span class="text-weight-medium">{{ notification.actor.display_name }}</span>
        {{ notificationText }}
      </q-item-label>
      <q-item-label caption>
        {{ formatDate(notification.created_at) }}
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-icon v-if="!notification.is_read" color="blue" name="circle" size="8px" />
    </q-item-section>
  </q-item>
</template>

<script setup lang="ts">
import type { Notification } from '@/models'
import type { Locale } from 'date-fns'
import { formatDistanceToNow } from 'date-fns'
import { enUS, ja, pt, tr } from 'date-fns/locale'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  notification: Notification
}>()

const emit = defineEmits<{
  click: [notification: Notification]
}>()

const { t, locale } = useI18n()

const localeMap: Record<string, Locale> = {
  en: enUS,
  pt: pt,
  ja: ja,
  tr: tr
}

const notificationText = computed(() => {
  switch (props.notification.type) {
    case 'activity_liked':
      return t('notifications.liked-your-activity')
    case 'activity_commented':
      return t('notifications.commented-on-your-activity')
    case 'follow_accepted':
      return t('notifications.accepted-your-follow')
    default:
      return ''
  }
})

function formatDate(dateStr: string): string {
  const dateLocale = localeMap[locale.value] || enUS
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: dateLocale })
}

function handleClick() {
  emit('click', props.notification)
}
</script>

<style scoped lang="sass">
.q-item
  border-radius: 8px
  margin-bottom: 4px
</style>
