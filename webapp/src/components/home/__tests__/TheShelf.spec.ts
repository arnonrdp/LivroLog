import { useUserStore } from '@/stores'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import TheShelf from '../TheShelf.vue'

const books = [{ id: 'B-1', title: 'Dune' }] as never

function mountShelf(shelfTexture?: string) {
  return mount(TheShelf, {
    props: { books, shelfTexture, userIdentifier: 'someone-else' } as never,
    global: {
      stubs: { QTooltip: true, BookDialog: true, GoodReadsImportDialog: true, EmptyShelfState: true }
    }
  })
}

describe('TheShelf shelf material', () => {
  it('renders the material of the shelf owner, not the signed-in visitor', () => {
    useUserStore().setMe({ id: 'U-visitor', username: 'visitor', shelf_texture: 'marble' } as never)

    const style = mountShelf('slate').find('section').attributes('style') || ''

    expect(style).toContain('slate')
    expect(style).not.toContain('marble')
  })

  it('falls back to wood when the owner has no material set', () => {
    const style = mountShelf(undefined).find('section').attributes('style') || ''

    expect(style).toContain('shelfleft')
  })
})
