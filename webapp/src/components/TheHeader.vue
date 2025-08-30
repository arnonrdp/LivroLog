<template>
  <q-header class="bg-accent text-black" elevated height-hint="48">
    <q-toolbar-title class="non-selectable">
      <router-link to="/"><img alt="Logotipo" src="/logo.svg" /></router-link>
    </q-toolbar-title>

    <q-tabs active-color="primary" indicator-color="primary">
      <q-route-tab
        v-for="t in tabs"
        :key="t.name"
        active-class="tab--active text-primary"
        :exact="t.name === 'home'"
        :icon="t.icon"
        :name="t.name"
        :to="t.name === 'settings' ? settingsTo : t.name === 'people' ? peopleTo : t.to"
      />
    </q-tabs>
  </q-header>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

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
</script>

<style scoped lang="sass">
header
  align-items: baseline
  display: flex
  text-align: left
  @media screen and (max-width: $breakpoint-sm-min)
    display: block
    padding-top: 1rem
    text-align: center

img[alt='Logotipo']
  padding: 0 1rem
  width: 13rem
  @media screen and (max-width: 320px)
    width: 15rem !important

.tab--active .q-tab__indicator
  background: var(--q-primary) !important
  opacity: 1
  transform: scale3d(1, 1, 1) !important

.tab--active .q-icon, .tab--active .q-tab__label
  color: var(--q-primary) !important
</style>
