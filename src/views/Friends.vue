<template>
  <Header />
  <h1>{{ $t("followFriends") }}</h1>
  <Loading />
  <p>{{ $t('friends.feature-under-dev') }}</p>
</template>

<script>
import Header from "@/components/TheHeader.vue";
import Loading from "@/components/Loading.vue";

export default {
  name: "Friends",
  components: { Header, Loading },
};
</script>
