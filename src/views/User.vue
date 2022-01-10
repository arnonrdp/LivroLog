<template>
  <div>Username: {{ $route.params.username }}</div>
  <h1>{{ getUserShelfName }}</h1>
</template>

<script>
import { mapGetters } from "vuex";

export default {
  computed: {
    ...mapGetters(["getUserShelfName"]),
  },
  props: ["username"],
};
</script>
