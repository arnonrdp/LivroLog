export interface Book {
  addedIn?: Date | string | number
  authors?: string[] | string
  description?: string
  id: string
  ISBN?: string
  link?: string
  readIn?: string | number
  thumbnail?: string | null
  title: string
}

export interface GoogleBook {
  kind: string
  id: string
  selfLink: string
  volumeInfo: {
    title: string
    authors: string[]
    description: string
    industryIdentifiers: [
      {
        type: string
        identifier: string
      }
    ]
    imageLinks: {
      smallThumbnail: string
      thumbnail: string
    }
  }
}
