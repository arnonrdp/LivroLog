<template>
  <q-header class="bg-accent text-black header-nav" elevated height-hint="48">
    <q-toolbar-title class="non-selectable logo-container">
      <router-link :to="authStore.isAuthenticated ? '/' : '/login'"><img alt="Logotipo" src="/logo.svg" /></router-link>
    </q-toolbar-title>

    <!-- Authenticated User Navigation -->
    <q-tabs v-if="authStore.isAuthenticated" active-color="primary" class="nav-tabs" indicator-color="primary">
      <LiquidGlassNav />
      <q-route-tab
        v-for="t in tabs"
        :key="t.name"
        active-class="tab--active text-primary"
        class="tab-item"
        :exact="t.name === 'home'"
        :icon="t.icon"
        :name="t.name"
        :to="t.name === 'settings' ? settingsTo : t.name === 'people' ? peopleTo : t.to"
        @click="createRipple"
      />
    </q-tabs>

    <!-- Guest User Navigation -->
    <div v-else class="guest-nav">
      <q-btn color="primary" :label="$t('signup-signin')" no-caps outline rounded size="md" to="/login" unelevated />
    </div>
  </q-header>
</template>

<script setup>
import LiquidGlassNav from '@/components/navigation/LiquidGlassNav.vue'
import { useAuthStore } from '@/stores'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const authStore = useAuthStore()

const tabs = [
  { name: 'home', icon: 'img:/books.svg', to: '/' },
  { name: 'add', icon: 'search', to: '/add' },
  { name: 'people', icon: 'people', to: '/people' },
  { name: 'settings', icon: 'settings', to: '/settings' }
]

const peopleTo = computed(() => {
  const path = route.path || '/'
  if (path.startsWith('/people')) return '/people'
  const segments = path.split('/').filter(Boolean)
  const reserved = new Set(['add', 'people', 'settings'])
  if (segments.length === 1 && !reserved.has(segments[0])) return path
  return '/people'
})
const settingsTo = computed(() => `/settings/${route.params.tab || 'books'}`)

const createRipple = (event) => {
  const button = event.currentTarget
  const existingRipple = button.querySelector('.ripple')
  if (existingRipple) existingRipple.remove()

  const circle = document.createElement('span')
  const diameter = Math.max(button.clientWidth, button.clientHeight)
  const radius = diameter / 2

  const rect = button.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top

  circle.style.width = circle.style.height = `${diameter}px`
  circle.style.left = `${x - radius}px`
  circle.style.top = `${y - radius}px`
  circle.classList.add('ripple')

  button.appendChild(circle)

  window.setTimeout(() => circle.remove(), 600)
}
</script>

<style scoped lang="sass">
.header-nav
  align-items: center
  display: flex
  text-align: left
  @media screen and (max-width: $breakpoint-xs-max)
    display: block
    padding-top: 1rem
    text-align: center

.logo-container
  align-items: center
  display: flex

.nav-tabs
  @media screen and (max-width: $breakpoint-xs-max)
    background: transparent
    border: 1px solid rgba(255, 255, 255, 0.3)
    border-radius: 28px
    bottom: 0
    box-shadow: 0 6px 6px rgba(0, 0, 0, 0.2), 0 0 20px rgba(0, 0, 0, 0.1)
    gap: 0
    height: 56px
    justify-content: space-evenly
    left: 12px
    margin-bottom: max(env(safe-area-inset-bottom, 12px), 16px)
    padding: 0
    position: fixed
    right: 12px
    width: calc(100% - 24px)
    z-index: 1000
    overflow: hidden
    @supports not (backdrop-filter: blur(4px))
      background: rgba(255, 255, 255, 0.95)

.tab-item
  position: relative
  overflow: visible
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease-out
  will-change: transform
  @media screen and (max-width: $breakpoint-xs-max)
    &:active
      transform: scale(1.08)
      opacity: 0.9
    :deep(.q-icon)
      transition: filter 0.2s ease-out
    &:active :deep(.q-icon)
      filter: brightness(1.15) drop-shadow(0 0 4px rgba(255, 255, 255, 0.6))
    :deep(.q-tab__indicator)
      display: none !important
    :deep(.q-focus-helper)
      display: none !important
    :deep(.q-hoverable:hover > .q-focus-helper)
      display: none !important
      opacity: 0 !important

img[alt='Logotipo']
  padding: 0 1rem
  width: 13rem
  @media screen and (max-width: 320px)
    width: 15rem !important

.tab--active
  @media screen and (max-width: $breakpoint-xs-max)
    &::before
      content: ''
      position: absolute
      top: 50%
      left: 50%
      transform: translate(-50%, -50%)
      width: 56px
      height: 56px
      background: rgba(0, 0, 0, 0.25)
      border-radius: 50%
      z-index: 0
      pointer-events: none

.tab--active .q-tab__indicator
  display: none !important
  opacity: 0 !important

.tab--active .q-icon, .tab--active .q-tab__label
  color: var(--q-primary) !important
  position: relative
  z-index: 1

.tab--active :deep(.q-tab__indicator)
  display: none !important
  opacity: 0 !important
  background: transparent !important

.nav-tabs :deep(.q-tab__indicator)
  display: none !important
  opacity: 0 !important

.nav-tabs :deep(.q-tabs__content)
  @media screen and (max-width: $breakpoint-xs-max)
    align-items: center
    justify-content: space-evenly

:deep(.ripple)
  position: absolute
  border-radius: 50%
  background: radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.4) 50%, rgba(255, 255, 255, 0) 100%)
  transform: scale(0)
  animation: ripple-animation 600ms ease-out
  pointer-events: none
  box-shadow: 0 0 8px rgba(255, 255, 255, 0.5)
  filter: blur(0.5px)

@keyframes ripple-animation
  0%
    transform: scale(0)
    opacity: 1
  50%
    opacity: 0.6
  100%
    transform: scale(4)
    opacity: 0

.guest-nav
  align-items: center
  display: flex
  flex: 1
  justify-content: flex-end
  padding: 0 1.5rem
  @media screen and (max-width: $breakpoint-xs-max)
    justify-content: center
    padding: 0.5rem 1rem 1rem
  :deep(.q-btn)
    border-width: 2px
    font-weight: 500
    padding: 0.5rem 1.5rem
    transition: all 0.2s ease
    &:hover
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1)
      transform: translateY(-1px)
</style>
