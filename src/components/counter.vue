v-<template>
  <p>
    {{ $t("message.counter") }}:
    <Tooltip label="Preserved State">{{ count }}</Tooltip>
  </p>
  <p><button @click="increment">+</button></p>
</template>

<script>
import { mapState } from "vuex";
import { mapActions } from "vuex";
import Tooltip from "@adamdehaven/vue-custom-tooltip";

export default {
  name: "Counter",
  components: { Tooltip },
  methods: {
    ...mapActions([
      "increment", // mapeia `this.increment()` para `this.$store.dispatch('increment')`
    ]),
  },
  computed: mapState([
    // mapeia this.count para store.state.count
    "count",
  ]),
};
</script>
