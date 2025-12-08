import { vi } from 'vitest'

export const mockAxios = {
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  defaults: {
    headers: {
      common: {},
    },
  },
  interceptors: {
    request: {
      use: vi.fn(),
    },
    response: {
      use: vi.fn(),
    },
  },
}

vi.mock('@/utils/axios', () => ({
  default: mockAxios,
}))

export const resetAxiosMocks = () => {
  mockAxios.get.mockReset()
  mockAxios.post.mockReset()
  mockAxios.put.mockReset()
  mockAxios.patch.mockReset()
  mockAxios.delete.mockReset()
}
