<template>
  <Header />
  <h1>{{ $t("followFriends") }}</h1>
</template>

<script>
import Header from "@/components/TheHeader.vue";

export default {
  name: "Friends",
  components: { Header },
};
</script>
