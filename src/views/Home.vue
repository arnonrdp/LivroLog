<template>
  <Header />
  <div class="home">
    <img alt="Vue logo" src="../assets/logo.png" />
    <HelloWorld />
  </div>
</template>

<script>
import HelloWorld from "@/components/HelloWorld.vue";
import Header from "@/components/TheHeader.vue";

export default {
  name: "Home",
  components: { HelloWorld, Header },
};
</script>
