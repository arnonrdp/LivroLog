import { config } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { vi, beforeEach, afterEach } from 'vitest'

// Mock native localStorage (for code that uses it directly)
const storage: Record<string, string> = {}
const localStorageMock = {
  getItem: vi.fn((key: string) => storage[key] || null),
  setItem: vi.fn((key: string, value: string) => {
    storage[key] = value
  }),
  removeItem: vi.fn((key: string) => {
    delete storage[key]
  }),
  clear: vi.fn(() => {
    Object.keys(storage).forEach((key) => delete storage[key])
  }),
  get length() {
    return Object.keys(storage).length
  },
  key: vi.fn((index: number) => Object.keys(storage)[index] || null),
}
Object.defineProperty(globalThis, 'localStorage', {
  value: localStorageMock,
  writable: true,
  configurable: true,
})

// Mock @vue/devtools-kit to prevent localStorage issues
vi.mock('@vue/devtools-kit', () => ({
  devtools: {
    hook: {
      on: vi.fn(),
      emit: vi.fn(),
    },
  },
  setupDevtoolsPlugin: vi.fn(),
}))

// Mock Quasar
vi.mock('quasar', async () => {
  const actual = await vi.importActual('quasar')
  return {
    ...actual,
    Notify: {
      create: vi.fn(),
    },
    LocalStorage: {
      getItem: vi.fn(() => null),
      setItem: vi.fn(),
      set: vi.fn(),
      clear: vi.fn(),
      remove: vi.fn(),
    },
  }
})

// Mock vue-router
vi.mock('vue-router', async () => {
  const actual = await vi.importActual('vue-router')
  return {
    ...actual,
    useRouter: () => ({
      push: vi.fn(),
      replace: vi.fn(),
      go: vi.fn(),
      back: vi.fn(),
      forward: vi.fn(),
    }),
    useRoute: () => ({
      params: {},
      query: {},
      path: '/',
    }),
  }
})

// Setup Pinia for each test
beforeEach(() => {
  setActivePinia(createPinia())
})

// Clear all mocks after each test
afterEach(() => {
  vi.clearAllMocks()
})

// Global test configuration
config.global.stubs = {
  // Stub router-link and router-view
  RouterLink: true,
  RouterView: true,
}
