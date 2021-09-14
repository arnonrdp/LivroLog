v-<template>
  <p>Counter: {{ count }}</p>
  <p><button @click="increment">+</button></p>
  <p>(Preserve State)</p>
</template>

<script>
import { mapState } from "vuex";
import { mapActions } from 'vuex'

export default {
  name: "Counter",
  methods: {
    ...mapActions([
      'increment', // mapeia `this.increment()` para `this.$store.dispatch('increment')`
    ]),
  },
  computed: mapState([
    // mapeia this.count para store.state.count
    "count",
  ]),
};
</script>
