<template>
  <div class="liquid-glass-nav">
    <!-- Glass sheet: transparency comes from a low white fill + a saturating backdrop blur.
         No CSS `filter` and no SVG filter on this element — either one creates a new backdrop
         root, which stops the blur from sampling the page and is what made the pill go opaque. -->
    <div class="glass-sheet"></div>
    <!-- Specular sweep across the top third: reads as glass thickness -->
    <div class="glass-specular"></div>
    <!-- Sliding active indicator; `--nav-index` / `--nav-count` are set from TheHeader -->
    <div class="glass-pill"></div>
  </div>
</template>

<script setup lang="ts">
// Purely visual. The active index is passed through CSS custom properties so that
// moving the pill never re-renders the tab list.
withDefaults(defineProps<{ count?: number; index?: number }>(), { count: 6, index: 0 })
</script>

<style scoped lang="sass">
.liquid-glass-nav
  border-radius: 30px
  inset: 0
  overflow: hidden
  pointer-events: none
  position: absolute
  z-index: -1
  @media screen and (min-width: $breakpoint-sm-min)
    display: none

.glass-sheet
  backdrop-filter: blur(16px) saturate(190%)
  -webkit-backdrop-filter: blur(16px) saturate(190%)
  background: rgba(255, 255, 255, 0.2)
  border-radius: inherit
  box-shadow: 0 10px 26px rgba(0, 0, 0, 0.28), inset 0 1.5px 0 rgba(255, 255, 255, 0.9), inset 0 -1.5px 0 rgba(255, 255, 255, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.45)
  inset: 0
  position: absolute

.glass-specular
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0))
  border-radius: 0 0 40px 40px
  height: 22px
  left: 14px
  position: absolute
  right: 14px
  top: 1px

.glass-pill
  background: rgba(255, 255, 255, 0.42)
  border-radius: 24px
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85), 0 2px 8px rgba(0, 0, 0, 0.14)
  height: 48px
  left: calc((var(--nav-index, 0) + 0.5) * (100% / var(--nav-count, 6)) - 24px)
  position: absolute
  top: 6px
  transition: left 0.32s cubic-bezier(0.34, 1.4, 0.5, 1)
  width: 48px

// Browsers without backdrop-filter get an honest solid pill instead of a broken blur
@supports not ((backdrop-filter: blur(16px)) or (-webkit-backdrop-filter: blur(16px)))
  .glass-sheet
    background: #fbfcfc
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(0, 0, 0, 0.06)
  .glass-specular
    display: none
</style>
