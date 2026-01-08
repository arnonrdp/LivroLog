import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import BookCoverPlaceholder from '../BookCoverPlaceholder.vue'

describe('BookCoverPlaceholder', () => {
  describe('Rendering', () => {
    it('should render an SVG element', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Test Book' }
      })

      expect(wrapper.find('svg').exists()).toBe(true)
    })

    it('should have the book-placeholder class', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Test Book' }
      })

      expect(wrapper.find('svg').classes()).toContain('book-placeholder')
    })

    it('should render title in uppercase', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'test book' }
      })

      const textContent = wrapper.find('text').text()
      expect(textContent).toBe('TEST BOOK')
    })
  })

  describe('Size prop', () => {
    it('should default to auto size', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Test' }
      })

      expect(wrapper.find('svg').classes()).toContain('size-auto')
    })

    it('should apply sm size class', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Test', size: 'sm' }
      })

      expect(wrapper.find('svg').classes()).toContain('size-sm')
    })

    it('should apply md size class', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Test', size: 'md' }
      })

      expect(wrapper.find('svg').classes()).toContain('size-md')
    })

    it('should apply lg size class', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Test', size: 'lg' }
      })

      expect(wrapper.find('svg').classes()).toContain('size-lg')
    })
  })

  describe('Title line breaking', () => {
    it('should keep short title on single line', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Short' }
      })

      const tspans = wrapper.findAll('tspan')
      expect(tspans).toHaveLength(1)
      expect(tspans[0]?.text()).toBe('SHORT')
    })

    it('should break long title into multiple lines', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'The Great Gatsby Novel' }
      })

      const tspans = wrapper.findAll('tspan')
      expect(tspans.length).toBeGreaterThan(1)
    })

    it('should truncate very long single word with ellipsis', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Supercalifragilisticexpialidocious' }
      })

      const textContent = wrapper.find('text').text()
      expect(textContent).toContain('...')
    })

    it('should limit to maximum 5 lines', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'One Two Three Four Five Six Seven Eight Nine Ten Eleven Twelve' }
      })

      const tspans = wrapper.findAll('tspan')
      expect(tspans.length).toBeLessThanOrEqual(5)
    })

    it('should handle empty title', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: '' }
      })

      const tspans = wrapper.findAll('tspan')
      expect(tspans).toHaveLength(0)
    })
  })

  describe('Background color generation', () => {
    it('should generate consistent color for same title', () => {
      const wrapper1 = mount(BookCoverPlaceholder, {
        props: { title: 'Test Book' }
      })
      const wrapper2 = mount(BookCoverPlaceholder, {
        props: { title: 'Test Book' }
      })

      const rect1 = wrapper1.find('rect')
      const rect2 = wrapper2.find('rect')

      expect(rect1.attributes('fill')).toBe(rect2.attributes('fill'))
    })

    it('should generate different colors for different titles', () => {
      const wrapper1 = mount(BookCoverPlaceholder, {
        props: { title: 'Book A' }
      })
      const wrapper2 = mount(BookCoverPlaceholder, {
        props: { title: 'Book B' }
      })

      const rect1 = wrapper1.find('rect')
      const rect2 = wrapper2.find('rect')

      // Note: Different titles MAY have same color due to hash collision,
      // but these specific titles should differ
      expect(rect1.attributes('fill')).not.toBe(rect2.attributes('fill'))
    })

    it('should use one of the predefined colors', () => {
      const wrapper = mount(BookCoverPlaceholder, {
        props: { title: 'Any Book' }
      })

      const validColors = [
        '#5c6bc0', // indigo
        '#7e57c2', // purple
        '#26a69a', // teal
        '#66bb6a', // green
        '#ffa726', // orange
        '#ef5350', // red
        '#42a5f5', // blue
        '#ab47bc' // violet
      ]

      const fill = wrapper.find('rect').attributes('fill')
      expect(validColors).toContain(fill)
    })
  })
})
