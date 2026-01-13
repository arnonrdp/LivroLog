export type NotificationType = 'activity_liked' | 'activity_commented' | 'follow_accepted'

export interface NotificationActor {
  id: string
  display_name: string
  username: string
  avatar?: string
}

export interface Notification {
  id: string
  type: NotificationType
  actor: NotificationActor
  data?: Record<string, unknown>
  activity_id?: string | null
  read_at: string | null
  is_read: boolean
  created_at: string
}

export interface NotificationMeta {
  total: number
  current_page: number
  per_page: number
  last_page: number
  unread_count: number
}

export interface NotificationListResponse {
  data: Notification[]
  meta: NotificationMeta
}

export interface UnreadCountResponse {
  unread_count: number
}

// WebSocket event payload from Laravel Reverb
export interface NewNotificationEvent {
  id: string
  type: NotificationType
  actor: NotificationActor
  data?: Record<string, unknown>
  activity_id?: string | null
  read_at: null
  is_read: false
  created_at: string
}
