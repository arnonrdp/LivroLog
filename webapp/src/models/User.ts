import type { Book } from '@/models'

export interface User {
  avatar?: string | null
  books?: Book[]
  created_at?: string
  display_name: string
  email_verified_at?: string | null
  email_verified?: boolean
  email: string
  followers?: User[]
  followers_count?: number
  following?: User[]
  following_count?: number
  google_id?: string | null
  has_pending_follow_request?: boolean
  has_password_set?: boolean
  has_google_connected?: boolean
  id: string
  is_private?: boolean
  is_following?: boolean
  is_follower?: boolean
  locale?: object | string
  modified_at?: string
  pending_follow_requests_count?: number
  role?: 'admin' | 'user'
  shelf_name?: string
  updated_at?: string
  username: string
}

export interface FollowRequest {
  id: number
  follower: User
  created_at: string
}

export interface AuthResponse {
  access_token: string
  token_type: string
  user: User
}
