<template>
  <q-page padding :style-fn="myTweak">
    <q-input v-model="filter" color="primary" dense debounce="300" flat :label="$t('friends.search-for-people')">
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
    </q-input>
    <q-table
      grid
      card-container-class="column"
      :rows="users"
      row-key="id"
      :filter="filter"
      :rows-per-page-options="[0]"
    >
      <!-- TODO: Inserir paginação com 10 itens por página   -->
      <template v-slot:item="props">
        <div class="flex flex-center justify-between q-my-sm text-secondary">
          <router-link :to="{ name: 'user', params: { username: props.row.username } }" class="row">
            <q-avatar>
              <q-img v-if="props.row.photoURL" :src="props.row.photoURL" alt="avatar" />
              <q-icon v-else size="md" name="person" />
            </q-avatar>
            <div class="column justify-center items-start q-ml-sm">
              <strong>{{ props.row.displayName }}</strong>
              <span>@{{ props.row.username }}</span>
            </div>
          </router-link>
          <q-chip
            v-if="this.getMyProfile.uid !== props.row.id"
            disable
            class="cursor-pointer non-selectable"
            color="primary"
            text-color="white"
            @click="props.row.following ? unfollow(props.row.username) : follow(props.row.username)"
          >
            {{ props.row.following ? $t("friends.following") : $t("friends.follow") }}
            <q-tooltip anchor="bottom middle" self="center middle" class="bg-black">
              {{ $t("friends.feature-under-dev") }}
            </q-tooltip>
          </q-chip>
        </div>
      </template>
      <template v-slot:no-data>
        <div class="full-width row flex-center q-gutter-sm">
          <q-icon size="2em" name="sentiment_dissatisfied" />
          <span>{{ $t("friends.no-one-found") }}</span>
        </div>
      </template>
    </q-table>
  </q-page>
</template>

<script>
import Loading from "@/components/Loading.vue";
import { mapGetters } from "vuex";

export default {
  components: { Loading },
  data: () => ({
    filter: "",
    friends: [],
    offset: 115,
    users: [],
  }),
  computed: {
    ...mapGetters(["getUsers", "getMyProfile"]),
  },
  async mounted() {
    await this.$store.dispatch("queryDBUsers");
    this.users = this.getUsers;
  },
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
button {
  height: 1.6rem;
}
</style>
