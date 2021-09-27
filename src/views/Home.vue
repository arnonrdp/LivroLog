<template>
  <Header />
  <Shelf />
</template>

<script>
import Header from "@/components/TheHeader.vue";
import Shelf from "@/components/Shelf.vue";

export default {
  name: "Home",
  title: "Livrero",
  components: { Header, Shelf },
  mounted() {
    localStorage.setItem("messi", "a7san wa7d");
  },
};
</script>

<style scoped>
* {
  user-select: none;
}
</style>
