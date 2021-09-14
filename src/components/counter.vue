v-<template>
  <div>Contador: {{ count }}</div>
</template>

<script>
import { mapState } from "vuex";

export default {
  name: "Counter",
  methods: {
    increment() {
      this.$store.commit("increment");
      console.log(this.$store.state.count);
    },
  },
  computed: mapState([
    // mapeia this.count para store.state.count
    "count",
  ]),
};
</script>
