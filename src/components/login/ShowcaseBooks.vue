<template>
  <section class="flex justify-around">
    <figure v-for="book of shuffledShowcase" :key="book.id">
      <div class="cursor-pointer" @click="goTo(book.link)">
        <img v-if="book.thumbnail" :src="book.thumbnail" :alt="$t('book.cover-image-alt', [book.title])" />
        <img v-else src="@/assets/no_cover.jpg" :alt="$t('book.cover-image-alt', [book.title])" />
      </div>
    </figure>
  </section>
</template>

<script setup lang="ts">
import type { Book } from '@/models'
import { useShowcaseStore } from '@/store/showcase'
import { onMounted, ref } from 'vue'

const showcase = useShowcaseStore()
const shuffledShowcase = ref<Book[]>([])

onMounted(async () => {
  await showcase.fetchShowcase()
  shuffledShowcase.value = shuffleArray(showcase.getShowcase)
})

function shuffleArray(array: Book[]): Book[] {
  const result = array.slice()
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(secureRandom() * (i + 1))
    ;[result[i], result[j]] = [result[j], result[i]]
  }
  return result
}

function secureRandom(): number {
  const buffer = new Uint8Array(1)
  window.crypto.getRandomValues(buffer)
  return buffer[0] / 255
}

function goTo(link: Book['link']) {
  window.open(link, '_blank')
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
