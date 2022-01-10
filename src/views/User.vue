<template>
  <Shelf :shelfName="this.$route.params.username" />
</template>

<script>
import Shelf from "../components/Shelf.vue";

export default {
  components: { Shelf },
};
</script>
