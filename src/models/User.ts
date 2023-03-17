import type { Book } from '@/models'

export interface User {
  uid: string
  displayName: string
  email: string
  locale: object | string
  modifiedAt?: Date
  password: string
  photoURL?: string
  shelfName?: string
  username: string
  books?: Book[]
  followers?: User[]
  following?: User[]
}
