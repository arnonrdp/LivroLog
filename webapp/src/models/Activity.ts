export type ActivityType = 'book_added' | 'book_started' | 'book_read' | 'review_written' | 'user_followed'

export interface ActivityUser {
  id: string
  display_name: string
  username: string
  avatar?: string
}

export interface ActivityBookSubject {
  type: 'Book'
  id: string
  title: string
  authors?: string
  thumbnail?: string
}

export interface ActivityUserSubject {
  type: 'User'
  id: string
  display_name: string
  username: string
  avatar?: string
}

export interface ActivityReviewSubject {
  type: 'Review'
  id: string
  rating: number
  book: {
    id: string
    title: string
    authors?: string
    thumbnail?: string
  }
}

export type ActivitySubject = ActivityBookSubject | ActivityUserSubject | ActivityReviewSubject | null

export interface Activity {
  id: string
  type: ActivityType
  created_at: string
  user: ActivityUser
  subject: ActivitySubject
  metadata?: Record<string, unknown>
}

export interface ActivityGroup {
  user: ActivityUser
  type: ActivityType
  date: string
  count: number
  activities: Activity[]
}

export interface FeedResponse {
  data: Activity[]
  grouped: ActivityGroup[]
  meta: {
    total: number
    current_page: number
    per_page: number
    last_page: number
  }
}
