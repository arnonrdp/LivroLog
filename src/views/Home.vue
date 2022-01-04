<template>
  <q-page class="q-mx-sm">
    <Shelf />
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";

export default {
  name: "Home",
  title: "Livrero",
  components: { Shelf },
};
</script>

<style scoped>
* {
  user-select: none;
}
</style>
