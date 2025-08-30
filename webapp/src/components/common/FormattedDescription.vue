<template>
  <div class="formatted-description">
    <template v-for="(block, index) in formattedDescription" :key="index">
      <p v-if="block.type === 'paragraph'" class="text-body2 text-grey-7 q-mb-sm">
        <span v-for="(segment, segIndex) in block.content" :key="segIndex" :class="getSegmentClass(segment.style)">
          {{ segment.text }}
        </span>
      </p>
      <ul v-else-if="block.type === 'list'" class="q-pl-md q-mb-sm">
        <li v-for="(item, itemIndex) in block.items" :key="itemIndex" class="text-body2 text-grey-7">
          <template v-if="Array.isArray(item)">
            <span v-for="(segment, segIndex) in item" :key="segIndex" :class="getSegmentClass(segment.style)">
              {{ segment.text }}
            </span>
          </template>
          <template v-else>{{ item }}</template>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface TextSegment {
  text: string
  style: string[]
}

interface DescriptionBlock {
  type: 'paragraph' | 'list'
  content?: TextSegment[]
  text?: string // Legacy support
  items?: TextSegment[][] | string[] // Legacy support
}

interface Props {
  formattedDescription: DescriptionBlock[] | null
  maxLength?: number
  showFullDescription?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  maxLength: 350,
  showFullDescription: false
})

const getSegmentClass = (styles: string[] = []) => {
  const classes: string[] = []
  if (styles.includes('bold')) classes.push('text-weight-bold')
  if (styles.includes('italic')) classes.push('text-italic')
  if (styles.includes('underline')) classes.push('text-underline')
  return classes.join(' ')
}

const formattedDescription = computed(() => {
  if (!props.formattedDescription) return []

  // Convert legacy format to new format if needed
  const normalized = props.formattedDescription.map((block) => {
    if (block.type === 'paragraph') {
      // If it has content array, it's already in new format
      if (block.content) return block
      // Otherwise convert from legacy text format
      if (block.text) {
        return {
          type: 'paragraph' as const,
          content: [{ text: block.text, style: [] as string[] }]
        }
      }
    } else if (block.type === 'list' && block.items) {
      // Check if items are already in new format
      if (block.items.length > 0 && Array.isArray(block.items[0])) {
        return block
      }
      // Convert from legacy string array format
      return {
        type: 'list' as const,
        items: (block.items as string[]).map((item) => [{ text: item, style: [] as string[] }])
      }
    }
    return block
  })

  if (!props.showFullDescription && props.maxLength) {
    let totalLength = 0
    const truncated: DescriptionBlock[] = []

    for (const block of normalized) {
      if (block.type === 'paragraph' && block.content) {
        const remaining = props.maxLength - totalLength
        if (remaining <= 0) break

        const blockText = block.content.map((s) => s.text).join('')
        if (blockText.length <= remaining) {
          truncated.push(block)
          totalLength += blockText.length
        } else {
          // Truncate at segment level
          let segmentTotal = 0
          const truncatedContent: TextSegment[] = []
          for (const segment of block.content) {
            const segRemaining = remaining - segmentTotal
            if (segRemaining <= 0) break

            if (segment.text.length <= segRemaining) {
              truncatedContent.push(segment)
              segmentTotal += segment.text.length
            } else {
              truncatedContent.push({
                text: segment.text.substring(0, segRemaining) + '...',
                style: segment.style
              })
              break
            }
          }
          truncated.push({ type: 'paragraph', content: truncatedContent })
          break
        }
      } else if (block.type === 'list' && block.items) {
        const listText = block.items.map((item) => (Array.isArray(item) ? item.map((s) => s.text).join('') : item)).join(' ')
        const remaining = props.maxLength - totalLength
        if (remaining <= 0) break

        if (listText.length <= remaining) {
          truncated.push(block)
          totalLength += listText.length
        } else if (block.items.length > 0) {
          const firstItem = block.items[0]
          const firstItemText = Array.isArray(firstItem) ? firstItem.map((s) => s.text).join('') : (firstItem as string)
          if (firstItemText) {
            truncated.push({
              type: 'paragraph',
              content: [
                {
                  text: '• ' + firstItemText.substring(0, Math.max(0, remaining - 2)) + '...',
                  style: []
                }
              ]
            })
          }
          break
        }
      }
    }

    return truncated
  }

  return normalized
})
</script>

<style scoped>
.formatted-description ul {
  list-style-type: disc;
  margin: 0;
  padding: 0 0 0 1.5rem;
}

.formatted-description li {
  margin-bottom: 0.25rem;
}

.formatted-description p:last-child,
.formatted-description ul:last-child {
  margin-bottom: 0;
}

.text-underline {
  text-decoration: underline;
}
</style>
