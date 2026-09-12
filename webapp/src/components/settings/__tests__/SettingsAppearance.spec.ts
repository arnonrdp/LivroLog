import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

// Hoist mock before imports
const mockAxios = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }))

vi.mock('@/utils/axios', () => ({ default: mockAxios }))

import { useUserStore } from '@/stores'
import SettingsAppearance from '../SettingsAppearance.vue'

function mountAppearance() {
  const userStore = useUserStore()
  userStore.setMe({ id: 'U-1', username: 'owner', shelf_texture: 'wood' } as never)

  const wrapper = mount(SettingsAppearance, {
    global: { mocks: { $t: (key: string) => key }, stubs: { QIcon: true } }
  })

  return { userStore, wrapper }
}

describe('SettingsAppearance', () => {
  it('persists the chosen material on the profile', async () => {
    const { userStore, wrapper } = mountAppearance()
    mockAxios.put.mockResolvedValueOnce({ data: { user: { id: 'U-1', username: 'owner', shelf_texture: 'slate' } } })

    await wrapper.find('[data-testid="shelf-texture-slate"]').trigger('click')

    expect(mockAxios.put).toHaveBeenCalledWith('/auth/me', { shelf_texture: 'slate' })
    expect(userStore.me.shelf_texture).toBe('slate')
  })

  it('rolls the choice back when saving fails', async () => {
    const { userStore, wrapper } = mountAppearance()
    mockAxios.put.mockRejectedValueOnce({ message: 'offline' })

    await wrapper.find('[data-testid="shelf-texture-slate"]').trigger('click')
    await vi.waitFor(() => expect(userStore.me.shelf_texture).toBe('wood'))
  })
})
