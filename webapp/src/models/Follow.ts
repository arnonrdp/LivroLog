import type { User } from './User'

export interface Follow {
  id: number
  follower_id: string
  following_id: string
  created_at: string
  updated_at: string
}

export interface FollowResponse {
  success: boolean
  message: string
  data?: {
    follower: Pick<User, 'id' | 'display_name' | 'username'>
    following: Pick<User, 'id' | 'display_name' | 'username'>
    following_count: number
    followers_count: number
  }
  code?: string
}


export interface FollowStatus {
  is_following: boolean
  is_followed_by: boolean
  mutual_follow: boolean
}
