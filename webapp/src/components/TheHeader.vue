<template>
  <q-header elevated class="bg-accent text-black" height-hint="48">
    <q-toolbar-title class="non-selectable">
      <q-badge align="top" class="absolute rotate-315" color="red" style="top: 5px; left: -5px">Beta</q-badge>
      <router-link to="/"><img alt="Logotipo" src="/logo.svg" /></router-link>
    </q-toolbar-title>

    <q-tabs active-color="primary">
      <q-route-tab icon="img:/books.svg" to="/" />
      <q-route-tab exact icon="search" to="/add" />
      <q-route-tab exact icon="people" to="/people" />
      <q-route-tab exact icon="settings" to="/settings" />
    </q-tabs>
  </q-header>
</template>

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
</style>
