export interface Review {
  id: string
  user_id: string
  book_id: string
  title?: string
  content: string
  rating: 1 | 2 | 3 | 4 | 5
  visibility_level: 'private' | 'friends' | 'public'
  is_spoiler: boolean
  helpful_count: number
  created_at: string
  updated_at: string
  user?: {
    id: string
    display_name: string
    username: string
    avatar?: string
  }
  book?: {
    id: string
    title: string
    thumbnail?: string
  }
}

export interface CreateReviewRequest {
  book_id: string
  title?: string
  content: string
  rating: 1 | 2 | 3 | 4 | 5
  visibility_level: 'private' | 'friends' | 'public'
  is_spoiler?: boolean
}

export interface UpdateReviewRequest {
  title?: string
  content?: string
  rating?: 1 | 2 | 3 | 4 | 5
  visibility_level?: 'private' | 'friends' | 'public'
  is_spoiler?: boolean
}
