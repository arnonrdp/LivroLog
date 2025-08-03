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
  following?: User[]
  google_id?: string | null
  id: string
  locale?: object | string
  modified_at?: string
  role?: 'admin' | 'user'
  shelf_name?: string
  updated_at?: string
  username: string
}

export interface AuthResponse {
  access_token: string
  token_type: string
  user: User
}
