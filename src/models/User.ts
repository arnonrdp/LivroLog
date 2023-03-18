/* eslint-disable @typescript-eslint/no-explicit-any */
import type { Book } from '@/models'

export interface User {
  books?: Book[]
  displayName: string
  email: string
  followers?: GoogleUser[]
  following?: GoogleUser[]
  locale: object | string
  modifiedAt?: Date
  password: string
  photoURL?: string
  shelfName?: string
  uid: string
  username: string
}

export interface GoogleUser {
  [field: string]: any
}
