export type ReadingStatus = 'want_to_read' | 'reading' | 'read' | 'abandoned' | 'on_hold' | 're_reading'
export type AsinStatus = 'pending' | 'processing' | 'completed' | 'failed'

export interface Book {
  addedIn?: Date | string | number
  amazon_asin?: string
  amazon_buy_link?: string
  amazon_region?: string
  asin_processed_at?: string
  asin_status?: AsinStatus
  authors?: string
  categories?: string | string[]
  created_at?: string
  description?: string
  edition?: string
  google_id?: string
  id: string
  industry_identifiers?: string | unknown[]
  isbn?: string
  ISBN?: string
  language: string
  link?: string
  pivot?: {
    book_id: string
    created_at?: string
    is_private?: boolean
    rating?: number
    read_at?: string
    read_in?: string
    reading_status?: ReadingStatus
    review?: string
    status?: 'to_read' | 'reading' | 'read' // Legacy field
    updated_at?: string
    user_id: string
  }
  publisher?: string
  readIn?: string | number
  thumbnail?: string | null
  title: string
  updated_at?: string
}

export interface GoogleBook {
  id: string
  kind: string
  selfLink: string
  volumeInfo: {
    authors: string[]
    categories?: string[]
    description: string
    imageLinks: {
      smallThumbnail: string
      thumbnail: string
    }
    industryIdentifiers: [
      {
        identifier: string
        type: string
      }
    ]
    language?: string
    pageCount?: number
    publishedDate?: string
    publisher?: string
    title: string
  }
}
