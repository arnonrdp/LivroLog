export interface Tag {
  id: string
  name: string
  color: string
  books_count?: number
  created_at: string
  updated_at: string
}

export interface TagMeta {
  colors: string[]
  suggestions: string[]
}

export interface TagListResponse {
  data: Tag[]
  meta: TagMeta
}

export interface TagResponse {
  data: Tag
  message?: string
}

export interface CreateTagRequest {
  name: string
  color: string
}

export interface UpdateTagRequest {
  name?: string
  color?: string
}

// Available tag colors (should match backend TagService::COLORS)
export const TAG_COLORS = [
  '#EF4444', // Red
  '#F97316', // Orange
  '#EAB308', // Yellow
  '#22C55E', // Green
  '#3B82F6', // Blue
  '#A855F7', // Purple
  '#EC4899', // Pink
  '#6B7280' // Gray
] as const
