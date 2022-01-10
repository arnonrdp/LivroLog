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
        :rows="this.friends"
        :columns="columns"
        row-key="name"
        :filter="filter"
        hide-bottom
      >
        <template v-slot:item="props">
          <div class="flex justify-between q-my-sm text-secondary">
            <router-link :to="'/user/' + props.row.shelfName" class="row">
              <q-avatar>
                <q-img v-if="props.row.photoURL" :src="props.row.photoURL" alt="avatar" />
                <q-icon v-else size="md" name="person" />
              </q-avatar>
              <div class="column items-start q-ml-sm">
                <strong>{{ props.row.name }}</strong>
                <span>@{{ props.row.shelfName }}</span>
              </div>
            </router-link>
            <q-btn
              rounded
              size="sm"
              disable
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
import { collection, getDocs, query } from "@firebase/firestore";
import { db } from "../firebase";

export default {
  name: "Friends",
  components: { Loading },
  data: () => ({
    friends: [],
    filter: "",
    search: "",
    columns: [
      {
        name: "photoURL",
        label: "Photo",
        field: "photoURL",
      },
      {
        name: "name",
        label: "Name",
        field: "name",
      },
    ],
  }),
  computed: {
    ...mapGetters(["getUserShelfName"]),
  },
  methods: {
    async fetchFriends() {
      const response = await this.$axios.get("/api/friends");
      this.friends = response.data;
    },

    async queryUsersFromDB() {
      await getDocs(collection(db, "users"))
        .then((querySnapshot) => (this.friends = querySnapshot.docs.map((doc) => doc.data())))
        .catch((error) => console.error(error));
    },
  },
  created() {
    // TODO: Não fazer a consulta ao DB toda vez que a página é carregada
    // this.fetchFriends();
    this.queryUsersFromDB();
  },
};
</script>
