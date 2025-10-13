<template>
  <div class="liquid-glass-nav">
    <!-- Lens distortion SVG filter -->
    <svg style="position: absolute; width: 0; height: 0">
      <defs>
        <filter id="nav-lens-filter" filterUnits="objectBoundingBox" height="100%" width="100%" x="0%" y="0%">
          <feComponentTransfer in="SourceAlpha" result="alpha">
            <feFuncA type="identity" />
          </feComponentTransfer>
          <feGaussianBlur in="alpha" result="blur" stdDeviation="30" />
          <feDisplacementMap in="SourceGraphic" in2="blur" scale="30" xChannelSelector="A" yChannelSelector="A" />
        </filter>
      </defs>
    </svg>

    <!-- Single unified glass layer -->
    <div class="glass-unified"></div>
  </div>
</template>

<script setup lang="ts">
// Componente puramente visual, sem lógica necessária
</script>

<style scoped lang="sass">
.liquid-glass-nav
  position: absolute
  top: 0
  left: 0
  width: 100%
  height: 100%
  overflow: hidden
  border-radius: 28px
  pointer-events: none
  z-index: -1
  // Apenas em telas mobile (xs)
  @media screen and (min-width: $breakpoint-sm-min)
    display: none

.glass-unified
  position: absolute
  inset: 0
  border-radius: inherit
  pointer-events: none
  z-index: 0
  backdrop-filter: blur(4px)
  -webkit-backdrop-filter: blur(4px)
  background: rgba(255, 255, 255, 0.25)
  filter: url(#nav-lens-filter) saturate(120%) brightness(1.15)
  box-shadow: inset 1px 1px 0 rgba(255, 255, 255, 0.75), inset 0 0 5px rgba(255, 255, 255, 0.75)

@supports not (backdrop-filter: blur(4px))
  .glass-unified
    background: rgba(255, 255, 255, 0.85)
</style>
