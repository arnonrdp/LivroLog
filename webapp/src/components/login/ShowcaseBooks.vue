<template>
  <section class="flex justify-around">
    <figure v-for="book of shuffledShowcase" :key="book.id">
      <div>
        <img v-if="book.thumbnail" :alt="`Cover of ${book.title}`" :src="book.thumbnail" />
        <img v-else :alt="`No cover available for ${book.title}`" src="@/assets/no_cover.jpg" />
      </div>
    </figure>
  </section>
</template>

<script setup lang="ts">
import type { Book } from '@/models'
import { useBookStore } from '@/stores'
import { onMounted, ref } from 'vue'

const bookStore = useBookStore()
const shuffledShowcase = ref<Book[]>([])

onMounted(async () => {
  const showcaseBooks = await bookStore.getBooks({ sort_by: 'popular' })
  shuffledShowcase.value = shuffleArray(showcaseBooks || [])
})

function shuffleArray(array: Book[]): Book[] {
  const result = array.slice()
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(secureRandom() * (i + 1))
    const temp = result[i]
    result[i] = result[j]!
    result[j] = temp!
  }
  return result
}

function secureRandom(): number {
  const buffer = new Uint8Array(1)
  window.crypto.getRandomValues(buffer)
  return buffer[0]! / 255
}
</script>

<style scoped>
section > figure {
  height: 14rem;
  margin: 0 1.5rem;
}

section > figure img {
  height: 11rem;
}
</style>
