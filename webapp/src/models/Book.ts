export interface Book {
  addedIn?: Date | string | number
  authors?: string
  created_at?: string
  description?: string
  edition?: string
  id: string
  isbn?: string
  ISBN?: string
  language: string
  link?: string
  pivot?: {
    book_id: string
    created_at?: string
    rating?: number
    read_at?: string
    read_in?: string
    review?: string
    status: 'to_read' | 'reading' | 'read'
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
