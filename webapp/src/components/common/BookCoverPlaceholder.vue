<template>
  <svg :class="['book-placeholder', sizeClass]" viewBox="0 0 200 300" xmlns="http://www.w3.org/2000/svg">
    <rect width="200" height="300" :fill="backgroundColor" rx="4" />
    <text
      x="100"
      y="150"
      text-anchor="middle"
      dominant-baseline="middle"
      fill="white"
      font-family="system-ui, -apple-system, sans-serif"
      font-size="18"
      font-weight="500"
    >
      <tspan v-for="(line, i) in titleLines" :key="i" x="100" :dy="i === 0 ? -((titleLines.length - 1) * 12) : 24">
        {{ line }}
      </tspan>
    </text>
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  title: string
  size?: 'auto' | 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'auto'
})

const sizeClass = computed(() => `size-${props.size}`)

// Generate a consistent color based on the title
const backgroundColor = computed(() => {
  const hash = props.title.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
  const colors = [
    '#5c6bc0', // indigo
    '#7e57c2', // purple
    '#26a69a', // teal
    '#66bb6a', // green
    '#ffa726', // orange
    '#ef5350', // red
    '#42a5f5', // blue
    '#ab47bc' // violet
  ]
  return colors[hash % colors.length]
})

// Break the title into lines that fit the SVG
const titleLines = computed(() => {
  const maxCharsPerLine = 12
  const maxLines = 5
  const words = props.title.toUpperCase().split(' ')
  const lines: string[] = []
  let currentLine = ''

  for (const word of words) {
    const testLine = currentLine ? `${currentLine} ${word}` : word

    if (testLine.length > maxCharsPerLine) {
      if (currentLine) {
        lines.push(currentLine)
        currentLine = word.length > maxCharsPerLine ? word.slice(0, maxCharsPerLine - 1) + '...' : word
      } else {
        // Single word too long
        lines.push(word.slice(0, maxCharsPerLine - 1) + '...')
        currentLine = ''
      }
    } else {
      currentLine = testLine
    }

    if (lines.length >= maxLines) break
  }

  if (currentLine && lines.length < maxLines) {
    lines.push(currentLine)
  }

  // If we hit max lines and there's more content, add ellipsis to last line
  if (lines.length === maxLines && (currentLine !== lines[lines.length - 1] || words.length > lines.join(' ').split(' ').length)) {
    const lastLine = lines[lines.length - 1]
    if (lastLine && !lastLine.endsWith('...')) {
      lines[lines.length - 1] = lastLine.slice(0, maxCharsPerLine - 3) + '...'
    }
  }

  return lines
})
</script>

<style scoped>
.book-placeholder {
  display: block;
  border-radius: 4px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Auto size - inherits from parent container */
.size-auto {
  width: 100%;
  height: auto;
  max-width: 200px;
}

.size-sm {
  width: 80px;
  height: 120px;
}

.size-md {
  width: 128px;
  height: 192px;
}

.size-lg {
  width: 200px;
  height: 300px;
}
</style>
