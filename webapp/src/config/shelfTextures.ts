import type { CSSProperties } from 'vue'
import woodLeft from '@/assets/textures/shelfleft.jpg'
import woodRight from '@/assets/textures/shelfright.jpg'
import woodCenter from '@/assets/textures/shelfcenter.jpg'

export const shelfTextureIds = [
  'wood',
  'marble',
  'black-marble',
  'granite',
  'slate',
  'travertine',
  'concrete',
  'glass',
  'smoked-glass',
  'steel',
  'terrazzo'
] as const

export type ShelfTextureId = (typeof shelfTextureIds)[number]

// Book bottoms sit just in front of the rear/floor junction, leaving the
// foreground surface and front lip visible. Coordinates are in the 146px row.
// Keep a common 24px top clearance by fitting covers to each compartment.
const bookSupportY: Record<ShelfTextureId, number> = {
  wood: 139,
  marble: 128,
  'black-marble': 129,
  granite: 127,
  slate: 129,
  travertine: 127,
  concrete: 127,
  glass: 126,
  'smoked-glass': 128,
  steel: 127,
  terrazzo: 133
}

const images = import.meta.glob<string>('../assets/textures/materials/*/*.jpg', {
  eager: true,
  import: 'default',
  query: '?url'
})

export function resolveShelfTexture(value: string): ShelfTextureId {
  return shelfTextureIds.includes(value as ShelfTextureId) ? (value as ShelfTextureId) : 'wood'
}

// The 146px row pitch is shared with book positioning. Shading belongs to the
// raster images, so each material retains its own reflections and corner shadows.
export function shelfTextureStyle(value: string): CSSProperties {
  const id = resolveShelfTexture(value)
  const urls =
    id === 'wood'
      ? [woodLeft, woodRight, woodCenter]
      : ['shelfleft', 'shelfright', 'shelfcenter'].map((part) => images[`../assets/textures/materials/${id}/${part}.jpg`])
  return {
    '--shelf-book-bottom': `${146 - bookSupportY[id]}px`,
    '--shelf-book-height': `${bookSupportY[id] - 24}px`,
    backgroundImage: urls.map((url) => `url("${url}")`).join(', '),
    backgroundRepeat: id === 'wood' ? 'repeat-y, repeat-y, repeat' : 'repeat-y, repeat-y, repeat-y',
    backgroundPosition: id === 'wood' ? 'top left, top right, 240px 0' : 'top left, top right, 48px 0',
    backgroundSize: id === 'wood' ? 'auto' : '48px 146px, 48px 146px, calc(100% - 96px) 146px'
  }
}

export function shelfTexturePreviewStyle(value: ShelfTextureId): CSSProperties {
  if (value === 'wood') return shelfTextureStyle(value)
  return {
    backgroundImage: `url("${images[`../assets/textures/materials/${value}/preview.jpg`]}")`,
    backgroundSize: '100% 100%',
    backgroundPosition: 'center'
  }
}
