<template>
  <q-page>
    <h1 class="text-h6">{{ $t("friends.followFriends") }}</h1>
    <Loading />
    <p>{{ $t("friends.feature-under-dev") }}</p>
  </q-page>
</template>

<script>
import Loading from "@/components/Loading.vue";

export default {
  name: "Friends",
  components: { Loading },
};
</script>
