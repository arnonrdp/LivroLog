<template>
  <div class="empty-shelf-state">
    <div class="empty-shelf-content">
      <q-icon color="grey-4" name="auto_stories" size="80px" />
      <h5 class="q-mt-md q-mb-sm text-white">{{ $t('empty-shelf-title') }}</h5>
      <p class="text-grey-3 q-mb-lg">{{ $t('empty-shelf-subtitle') }}</p>

      <div class="button-group">
        <q-btn color="primary" icon="add" :label="$t('add-book')" unelevated @click="goToAdd" />
        <q-btn color="secondary" flat icon="upload" :label="$t('import-goodreads')" @click="$emit('import')" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

defineEmits<{
  import: []
}>()

const router = useRouter()

function goToAdd() {
  router.push('/add')
}
</script>

<style scoped lang="sass">
.empty-shelf-state
  background-image: url('@/assets/textures/shelfleft.jpg'), url('@/assets/textures/shelfright.jpg'), url('@/assets/textures/shelfcenter.jpg')
  background-repeat: repeat-y, repeat-y, repeat
  background-position: top left, top right, 240px 0
  border-radius: 6px
  min-height: 302px
  padding: 3rem
  display: flex
  align-items: center
  justify-content: center

.empty-shelf-content
  text-align: center
  max-width: 500px

  h5
    font-size: 1.5rem
    font-weight: 500
    margin: 0

  p
    font-size: 1rem
    margin: 0

.button-group
  display: flex
  gap: 1rem
  justify-content: center
  flex-wrap: wrap
</style>
