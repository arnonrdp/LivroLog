import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ref } from 'vue'
import type { StatsResponse } from '@/models'

// Hoist mock before imports
const mockAxios = vi.hoisted(() => ({
  get: vi.fn()
}))

vi.mock('@/utils/axios', () => ({
  default: mockAxios
}))

// Mock vue-echarts
vi.mock('vue-echarts', () => ({
  default: {
    name: 'VChart',
    props: ['option', 'autoresize'],
    template: '<div class="mock-chart"></div>'
  }
}))

// Mock echarts
vi.mock('echarts/core', () => ({
  use: vi.fn()
}))

vi.mock('echarts/charts', () => ({
  BarChart: {},
  RadarChart: {}
}))

vi.mock('echarts/components', () => ({
  GridComponent: {},
  LegendComponent: {},
  RadarComponent: {},
  TitleComponent: {},
  TooltipComponent: {}
}))

vi.mock('echarts/renderers', () => ({
  CanvasRenderer: {}
}))

// Mock i18n with proper ref for locale
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
    locale: ref('pt')
  })
}))

import ReadingStats from '../ReadingStats.vue'

const globalStubs = {
  global: {
    stubs: {
      'q-spinner': { template: '<div class="q-spinner-stub"></div>' },
      'q-card': { template: '<div class="q-card-stub"><slot /></div>' },
      'q-card-section': { template: '<div class="q-card-section-stub"><slot /></div>' },
      'q-space': { template: '<div class="q-space-stub"></div>' },
      'q-btn-toggle': { template: '<div class="q-btn-toggle-stub"></div>' },
      'v-chart': { template: '<div class="v-chart-stub"></div>' }
    },
    mocks: {
      $t: (key: string) => key
    }
  }
}

describe('ReadingStats', () => {
  const mockStatsResponse: StatsResponse = {
    by_status: {
      want_to_read: 10,
      reading: 3,
      read: 45,
      abandoned: 2,
      on_hold: 1,
      re_reading: 0
    },
    by_month: [
      { year: 2024, month: 1, count: 5 },
      { year: 2024, month: 2, count: 3 },
      { year: 2024, month: 3, count: 8 }
    ],
    by_category: [
      {
        main_category: 'Fiction',
        total: 25,
        subcategories: [
          { name: 'Fantasy', count: 15 },
          { name: 'Adventure', count: 10 }
        ]
      },
      {
        main_category: 'Non-Fiction',
        total: 10,
        subcategories: [
          { name: 'Biography', count: 10 }
        ]
      }
    ]
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('Loading State', () => {
    it('should not show stats section while loading', async () => {
      mockAxios.get.mockImplementation(() => new Promise(() => {})) // Never resolves

      const wrapper = mount(ReadingStats, {
        props: { username: 'testuser' },
        ...globalStubs
      })

      // Stats section should not be visible while loading
      expect(wrapper.find('.reading-stats').exists()).toBe(false)
    })
  })

  describe('Data Display', () => {
    it('should fetch stats on mount', async () => {
      mockAxios.get.mockResolvedValue({ data: mockStatsResponse })

      mount(ReadingStats, {
        props: { username: 'testuser' },
        ...globalStubs
      })

      await flushPromises()

      expect(mockAxios.get).toHaveBeenCalledWith('/users/testuser/stats')
    })

    it('should display reading-stats section when data is loaded', async () => {
      mockAxios.get.mockResolvedValue({ data: mockStatsResponse })

      const wrapper = mount(ReadingStats, {
        props: { username: 'testuser' },
        ...globalStubs
      })

      await flushPromises()

      expect(wrapper.find('.reading-stats').exists()).toBe(true)
    })

    it('should not display content when user has no books', async () => {
      const emptyStats: StatsResponse = {
        by_status: {
          want_to_read: 0,
          reading: 0,
          read: 0,
          abandoned: 0,
          on_hold: 0,
          re_reading: 0
        },
        by_month: [],
        by_category: []
      }

      mockAxios.get.mockResolvedValue({ data: emptyStats })

      const wrapper = mount(ReadingStats, {
        props: { username: 'testuser' },
        ...globalStubs
      })

      await flushPromises()

      expect(wrapper.find('.reading-stats').exists()).toBe(false)
    })
  })

  describe('API Error Handling', () => {
    it('should handle API errors gracefully', async () => {
      mockAxios.get.mockRejectedValue(new Error('Network error'))

      const wrapper = mount(ReadingStats, {
        props: { username: 'testuser' },
        ...globalStubs
      })

      await flushPromises()

      // Should not show stats section after error
      expect(wrapper.find('.reading-stats').exists()).toBe(false)
    })
  })

  describe('Username Changes', () => {
    it('should refetch data when username prop changes', async () => {
      mockAxios.get.mockResolvedValue({ data: mockStatsResponse })

      const wrapper = mount(ReadingStats, {
        props: { username: 'user1' },
        ...globalStubs
      })

      await flushPromises()

      expect(mockAxios.get).toHaveBeenCalledWith('/users/user1/stats')

      await wrapper.setProps({ username: 'user2' })
      await flushPromises()

      expect(mockAxios.get).toHaveBeenCalledWith('/users/user2/stats')
      expect(mockAxios.get).toHaveBeenCalledTimes(2)
    })
  })

  describe('Empty Username', () => {
    it('should not fetch if username is empty', async () => {
      mount(ReadingStats, {
        props: { username: '' },
        ...globalStubs
      })

      await flushPromises()

      expect(mockAxios.get).not.toHaveBeenCalled()
    })
  })
})

describe('ReadingStats - hasData computed', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('should return true when there are books by status', async () => {
    const stats: StatsResponse = {
      by_status: {
        want_to_read: 0,
        reading: 0,
        read: 1,
        abandoned: 0,
        on_hold: 0,
        re_reading: 0
      },
      by_month: [],
      by_category: []
    }

    mockAxios.get.mockResolvedValue({ data: stats })

    const wrapper = mount(ReadingStats, {
      props: { username: 'testuser' },
      ...globalStubs
    })

    await flushPromises()

    expect(wrapper.find('.reading-stats').exists()).toBe(true)
  })

  it('should return true when there are books by month', async () => {
    const stats: StatsResponse = {
      by_status: {
        want_to_read: 0,
        reading: 0,
        read: 0,
        abandoned: 0,
        on_hold: 0,
        re_reading: 0
      },
      by_month: [{ year: 2024, month: 1, count: 1 }],
      by_category: []
    }

    mockAxios.get.mockResolvedValue({ data: stats })

    const wrapper = mount(ReadingStats, {
      props: { username: 'testuser' },
      ...globalStubs
    })

    await flushPromises()

    expect(wrapper.find('.reading-stats').exists()).toBe(true)
  })

  it('should return true when there are books by category', async () => {
    const stats: StatsResponse = {
      by_status: {
        want_to_read: 0,
        reading: 0,
        read: 0,
        abandoned: 0,
        on_hold: 0,
        re_reading: 0
      },
      by_month: [],
      by_category: [{ main_category: 'Fiction', total: 1, subcategories: [] }]
    }

    mockAxios.get.mockResolvedValue({ data: stats })

    const wrapper = mount(ReadingStats, {
      props: { username: 'testuser' },
      ...globalStubs
    })

    await flushPromises()

    expect(wrapper.find('.reading-stats').exists()).toBe(true)
  })
})
