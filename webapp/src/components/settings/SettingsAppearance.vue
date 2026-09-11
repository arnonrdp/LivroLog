<template>
  <section aria-labelledby="appearance-title">
    <h2 id="appearance-title" class="text-h6 q-mt-none q-mb-xs">{{ $t('appearance.title') }}</h2>
    <p class="text-grey-7 q-mb-lg">{{ $t('appearance.description') }}</p>

    <div aria-hidden="true" class="shelf-preview q-mb-lg" :style="appearance.shelfStyle">
      <div class="preview-books">
        <span class="preview-book book-one">LivroLog</span>
        <span class="preview-book book-two">{{ $t('appearance.read') }}</span>
        <span class="preview-book book-three">{{ $t('appearance.stories') }}</span>
      </div>
    </div>

    <div :aria-label="$t('appearance.title')" class="texture-grid" role="group">
      <button
        v-for="texture in shelfTextureIds"
        :key="texture"
        :aria-pressed="appearance.selectedTexture === texture"
        class="texture-option"
        :class="{ selected: appearance.selectedTexture === texture }"
        :data-testid="`shelf-texture-${texture}`"
        type="button"
        @click="appearance.selectTexture(texture)"
      >
        <span aria-hidden="true" class="texture-swatch" :style="shelfTexturePreviewStyle(texture)" />
        <span class="texture-label">
          {{ $t(`appearance.materials.${texture}`) }}
          <q-icon v-if="appearance.selectedTexture === texture" color="teal" name="check_circle" size="20px" />
        </span>
      </button>
    </div>
    <p class="text-caption text-grey-7 q-mt-md" role="status">
      {{ $t('appearance.saved', { material: $t(`appearance.materials.${appearance.selectedTexture}`) }) }}
    </p>
  </section>
</template>

<script setup lang="ts">
import { shelfTextureIds, shelfTexturePreviewStyle } from '@/config/shelfTextures'
import { useAppearanceStore } from '@/stores/appearance'

const appearance = useAppearanceStore()
</script>

<style scoped>
.texture-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 16px;
}
.texture-option {
  padding: 0;
  overflow: hidden;
  border: 2px solid #ddd;
  border-radius: 10px;
  background: white;
  color: #263238;
  cursor: pointer;
  text-align: left;
  font: inherit;
}
.texture-option.selected {
  border-color: #00897b;
  box-shadow: 0 0 0 1px #00897b;
}
.texture-option:focus-visible {
  outline: 3px solid #00695c;
  outline-offset: 4px;
}
.texture-swatch {
  display: block;
  height: 100px;
}
.texture-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 12px;
}
.shelf-preview {
  height: 146px;
  border-radius: 6px;
  overflow: hidden;
}
.preview-books {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 22px;
  height: 146px;
  box-sizing: border-box;
  padding-bottom: var(--shelf-book-bottom, 7px);
}
.preview-book {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 72px;
  height: var(--shelf-book-height, 115px);
  margin-bottom: 0;
  border-radius: 2px 4px 3px 1px;
  box-shadow:
    3px 1px 5px #0008,
    inset 4px 0 #ffffff25;
  color: white;
  font-family: Georgia, serif;
  font-size: 13px;
}
.book-one {
  background: #264d4b;
}
.book-two {
  background: #904331;
  height: calc(var(--shelf-book-height, 115px) - 10px);
}
.book-three {
  background: #273b55;
  height: calc(var(--shelf-book-height, 115px) - 5px);
}
</style>
