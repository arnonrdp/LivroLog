<template>
  <q-page padding :style-fn="myTweak">
    <q-input v-model="filter" color="primary" dense debounce="300" flat :label="$t('friends.search-for-people')">
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
    </q-input>
    <q-table grid card-container-class="column" :rows="users" row-key="id" :filter="filter" hide-bottom>
      <template v-slot:item="props">
        <div class="flex flex-center justify-between q-my-sm text-secondary">
          <router-link :to="{ name: 'user_child', params: { username: props.row.shelfName } }" class="row">
            <q-avatar>
              <q-img v-if="props.row.photoURL" :src="props.row.photoURL" alt="avatar" />
              <q-icon v-else size="md" name="person" />
            </q-avatar>
            <div class="column justify-center items-start q-ml-sm">
              <strong>{{ props.row.name }}</strong>
              <span>@{{ props.row.shelfName }}</span>
            </div>
          </router-link>
          <q-btn
            v-show="this.getMyProfile.uid !== props.row.id"
            disable
            color="primary"
            size="sm"
            stretch="false"
            :title="$t('friends.feature-under-dev')"
            :label="props.row.following ? $t('friends.following') : $t('friends.follow')"
            @click="props.row.following ? unfollow(props.row.shelfName) : follow(props.row.shelfName)"
          />
        </div>
      </template>
    </q-table>
  </q-page>
</template>

<script>
import Loading from "@/components/Loading.vue";
import { mapGetters } from "vuex";

export default {
  name: "Friends",
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
