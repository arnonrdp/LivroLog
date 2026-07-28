<template>
  <q-header class="bg-accent text-black header-nav" elevated height-hint="48">
    <q-toolbar-title class="non-selectable logo-container">
      <router-link to="/"><img alt="Logotipo" src="/logo.svg" /></router-link>
    </q-toolbar-title>

    <!-- Mobile: notifications live in the header (top right), not in the bottom navbar.
         v-if (not CSS) so only one NotificationBell instance subscribes to Echo at a time.
         Wrapper div because NotificationBell is multi-root and drops scoped classes. -->
    <div v-if="authStore.isAuthenticated && $q.screen.lt.sm" class="notification-bell-mobile">
      <NotificationBell />
    </div>

    <q-space />

    <!-- Authenticated User Navigation -->
    <q-tabs v-if="authStore.isAuthenticated" active-color="primary" class="nav-tabs" indicator-color="primary">
      <LiquidGlassNav :count="navNames.length" :index="activeNavIndex" :style="{ '--nav-count': navNames.length, '--nav-index': activeNavIndex }" />
      <q-route-tab
        v-for="t in tabsBeforeSettings"
        :key="t.name"
        active-class="tab--active text-primary"
        class="tab-item"
        :exact="t.name === 'home'"
        :icon="t.icon"
        :name="t.name"
        :ripple="!$q.screen.lt.sm"
        :to="t.name === 'people' ? peopleTo : t.name === 'admin' ? adminTo : t.to"
        @click="createRipple"
      />
      <!-- Notification Bell (desktop only; on mobile it sits in the header) -->
      <NotificationBell v-if="!$q.screen.lt.sm" class="notification-bell" />
      <!-- Settings Tab -->
      <q-route-tab
        active-class="tab--active text-primary"
        class="tab-item"
        icon="settings"
        name="settings"
        :ripple="!$q.screen.lt.sm"
        :to="settingsTo"
        @click="createRipple"
      />
      <!-- Admin Tab (last) -->
      <q-route-tab
        v-if="isAdmin"
        active-class="tab--active text-primary"
        class="tab-item"
        icon="admin_panel_settings"
        name="admin"
        :ripple="!$q.screen.lt.sm"
        :to="adminTo"
        @click="createRipple"
      />
    </q-tabs>

    <!-- Guest User Navigation -->
    <div v-else class="guest-nav">
      <q-btn
        class="q-mr-sm"
        color="primary"
        data-testid="header-signin-btn"
        :label="$t('signin')"
        no-caps
        outline
        rounded
        size="md"
        @click="openLogin"
      />
      <q-btn color="primary" data-testid="header-signup-btn" :label="$t('signup')" no-caps rounded size="md" @click="openRegister" />
    </div>
  </q-header>
</template>

<script setup lang="ts">
import LiquidGlassNav from '@/components/navigation/LiquidGlassNav.vue'
import NotificationBell from '@/components/NotificationBell.vue'
import { useAuthStore, useUserStore } from '@/stores'
import { useQuasar } from 'quasar'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const $q = useQuasar()
const route = useRoute()
const authStore = useAuthStore()
const userStore = useUserStore()

const isAdmin = computed(() => userStore.me.role === 'admin')

const baseTabs = [
  { name: 'feed', icon: 'rss_feed', to: '/feed' },
  { name: 'home', icon: 'img:/books.svg', to: '/home' },
  { name: 'add', icon: 'search', to: '/add' },
  { name: 'people', icon: 'people', to: '/people' },
  { name: 'settings', icon: 'settings', to: '/settings' }
]

const tabs = computed(() => {
  if (isAdmin.value) {
    return [...baseTabs, { name: 'admin', icon: 'admin_panel_settings', to: '/admin' }]
  }
  return baseTabs
})

const tabsBeforeSettings = computed(() => tabs.value.filter((t) => t.name !== 'settings' && t.name !== 'admin'))

// Mirrors the render order of the mobile tab strip so the glass pill can slide to the active slot
const navNames = computed(() => [...tabsBeforeSettings.value.map((t) => t.name), 'settings', ...(isAdmin.value ? ['admin'] : [])])

const activeNavIndex = computed(() => {
  const current = route.name === 'person' ? 'people' : String(route.name || '')
  const found = navNames.value.indexOf(current)
  return found === -1 ? 0 : found
})

const peopleTo = computed(() => {
  const path = route.path || '/'
  if (path.startsWith('/people')) return '/people'
  const segments = path.split('/').filter(Boolean)
  const reserved = new Set(['home', 'add', 'people', 'settings', 'search', 'books', 'feed'])
  if (segments.length === 1 && segments[0] && !reserved.has(segments[0])) return path
  return '/people'
})

const settingsTo = computed(() => `/settings/${route.params.tab || 'books'}`)
const adminTo = computed(() => `/admin/${route.params.tab || 'users'}`)

function openLogin() {
  authStore.openAuthModal('login')
}

function openRegister() {
  authStore.openAuthModal('register')
}

const createRipple = (event: globalThis.Event) => {
  // Mobile mimics iOS Liquid Glass: no ripple, just the round press blob (CSS) and the sliding pill
  if ($q.screen.lt.sm) return

  const mouseEvent = event as globalThis.MouseEvent
  const button = event.currentTarget as globalThis.HTMLElement
  const existingRipple = button.querySelector('.ripple')
  if (existingRipple) existingRipple.remove()

  const circle = document.createElement('span')
  const diameter = Math.max(button.clientWidth, button.clientHeight)
  const radius = diameter / 2

  const rect = button.getBoundingClientRect()
  const x = mouseEvent.clientX - rect.left
  const y = mouseEvent.clientY - rect.top

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
  min-height: 56px
  text-align: left
  @media screen and (max-width: $breakpoint-xs-max)
    justify-content: center
    padding: 0.75rem 0
    // No `position: relative` here: it would override Quasar's .fixed-top and put the
    // header back in the page flow, doubling the top offset (QLayout already pads for it).

.logo-container
  align-items: center
  display: flex
  flex-shrink: 0

// Border and box-shadow now live on the glass sheet inside LiquidGlassNav,
// so the tab strip itself stays transparent.
.nav-tabs
  @media screen and (max-width: $breakpoint-xs-max)
    background: transparent
    border: none
    border-radius: 30px
    bottom: 0
    box-shadow: none
    gap: 0
    height: 60px
    justify-content: space-evenly
    left: 12px
    margin-bottom: max(env(safe-area-inset-bottom, 12px), 16px)
    overflow: visible
    padding: 0
    position: fixed
    right: 12px
    width: calc(100% - 24px)
    z-index: 1000
    // Equal-width slots so the glass pill's `100% / count` math matches the real
    // item centers, and nothing overflows into Quasar's scroll arrows.
    :deep(.q-tab)
      flex: 1 1 0
      min-width: 0
      padding: 0
    :deep(.q-tabs__arrow)
      display: none !important

.tab-item
  position: relative
  overflow: visible
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease-out
  will-change: transform
  @media screen and (max-width: $breakpoint-xs-max)
    // Icons sit on a light glass sheet: dark ink with a soft light halo keeps them
    // legible over both pale and dark book covers.
    color: rgba(0, 0, 0, 0.72)
    text-shadow: 0 1px 2px rgba(255, 255, 255, 0.55)
    // iOS Liquid Glass press feedback: a soft round blob blooms under the finger
    // (no rectangular Material ripple — QTab's ripple is disabled on xs).
    &::before
      background: rgba(255, 255, 255, 0.45)
      border-radius: 50%
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7), 0 2px 6px rgba(0, 0, 0, 0.08)
      content: ''
      height: 44px
      left: 50%
      opacity: 0
      pointer-events: none
      position: absolute
      top: 50%
      transform: translate(-50%, -50%) scale(0.4)
      transition: opacity 0.15s ease-out, transform 0.22s cubic-bezier(0.34, 1.4, 0.5, 1)
      width: 44px
    &:active::before
      opacity: 1
      transform: translate(-50%, -50%) scale(1)
    &:active
      transform: scale(1.06)
    :deep(.q-tab__content)
      position: relative
      z-index: 1
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

// The sliding pill in LiquidGlassNav is the only active affordance on mobile,
// so nothing reflows on tap (the old ::before black circle is gone).
.tab--active .q-tab__indicator
  display: none !important
  opacity: 0 !important

.tab--active .q-icon, .tab--active .q-tab__label
  color: var(--q-primary) !important
  position: relative
  z-index: 1
  @media screen and (max-width: $breakpoint-xs-max)
    color: #12564f !important

// Apply CSS filter to colorize SVG image icons when tab is active
.tab--active :deep(.q-icon img)
  filter: invert(52%) sepia(89%) saturate(352%) hue-rotate(127deg) brightness(91%) contrast(87%)

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
  flex-shrink: 0
  height: 100%
  padding: 0 1.5rem
  @media screen and (max-width: $breakpoint-xs-max)
    justify-content: center
    padding: 0.75rem 1rem
  :deep(.q-btn)
    font-weight: 500
    padding: 0.5rem 1.25rem
    transition: all 0.2s ease
    &:hover
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1)
      transform: translateY(-1px)

// Hide q-space on mobile
.header-nav > :deep(.q-space)
  @media screen and (max-width: $breakpoint-xs-max)
    display: none

.notification-bell
  align-items: center
  display: flex
  margin: 0 4px

// Mobile-only instance: pinned to the header's right edge, logo stays centered
.notification-bell-mobile
  position: absolute
  right: 8px
  top: 50%
  transform: translateY(-50%)
</style>
