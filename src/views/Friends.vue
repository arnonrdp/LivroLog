<template>
  <q-page>
    <h1 class="text-h6">{{ $t("followFriends") }}</h1>
  </q-page>
  <Loading />
  <p>{{ $t("friends.feature-under-dev") }}</p>
</template>

<script>
import Loading from "@/components/Loading.vue";

export default {
  name: "Friends",
  components: { Loading },
};
</script>
