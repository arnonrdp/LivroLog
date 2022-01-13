<template>
  <q-page>
    <h1 class="text-h6">{{ $t("friends.look-for-people") }} ~ {{ $t("friends.follow-friends") }}</h1>
    <q-separator inset />
    <div class="flex-center q-pa-md row">
      <q-input
        v-model="filter"
        :label="$t('friends.search-for-people')"
        class="col-md-7 col-sm-8 col-xs-12 q-mb-md"
        prepend-icon="search"
        dense
        debounce="300"
        flat
        color="primary"
        hide-details
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
      </q-input>
      <q-table
        grid
        class="col-md-7 col-sm-8 col-xs-12"
        card-container-class="column"
        :rows="users"
        row-key="id"
        :filter="filter"
        hide-bottom
      >
        <template v-slot:item="props">
          <div class="flex justify-between q-my-sm text-secondary">
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
              v-show="this.getMyProfile.email !== props.row.email"
              disable
              no-caps
              color="primary"
              size="md"
              padding="xs lg"
              :label="props.row.following ? $t('friends.following') : $t('friends.follow')"
              @click="props.row.following ? unfollow(props.row.shelfName) : follow(props.row.shelfName)"
            />
          </div>
        </template>
      </q-table>
    </div>
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
    users: [],
  }),
  computed: {
    ...mapGetters(["getUsers", "getMyProfile"]),
  },
  async mounted() {
    await this.$store.dispatch("queryDBUsers");
    this.users = this.getUsers;
  },
};
</script>
