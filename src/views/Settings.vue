<template>
  <q-page padding :style-fn="myTweak">
    <router-view />
  </q-page>
</template>

<script>
export default {
  data: () => ({
    offset: 115,
  }),
  methods: {
    myTweak() {
      return { minHeight: `calc(100vh - ${this.offset}px)` };
    },
  },
};
</script>

<style scoped>
.q-page {
  margin: 0 auto;
  width: 32rem;
}
</style>
