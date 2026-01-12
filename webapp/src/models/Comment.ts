export interface CommentUser {
  id: string
  display_name: string
  username: string
  avatar?: string
}

export interface Comment {
  id: string
  content: string
  user: CommentUser
  activity_id: string
  created_at: string
  updated_at: string
  is_owner: boolean
}

export interface CommentResponse {
  success: boolean
  message: string
  data?: Comment
}

export interface CommentListResponse {
  data: Comment[]
  meta: {
    total: number
    current_page: number
    per_page: number
    last_page: number
  }
}
