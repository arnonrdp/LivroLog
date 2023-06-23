<template>
  <section class="flex justify-around">
    <figure v-for="book of showcase.getShowcase" :key="book.id">
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
import { onMounted } from 'vue'

const showcase = useShowcaseStore()

onMounted(async () => {
  await showcase.fetchShowcase()
  showcase.getShowcase.sort(() => Math.random() - 0.5)
})

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
