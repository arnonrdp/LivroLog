<template>
  <q-page>
    <h1 class="text-h6">{{ $t("friends.look-for-people") }} ~ {{ $t("friends.follow-friends") }}</h1>
    <q-separator inset />
    <q-card>
      <q-card-section>
        <q-input
          v-model="search"
          :label="$t('friends.search-for-people')"
          :placeholder="$t('friends.search-for-people')"
          prepend-icon="search"
          dense
          flat
          color="primary"
          hide-details
        />
      </q-card-section>
      <q-card-section>
        <!-- <q-item-section v-for="friend in friends" :key="friend">
          <q-item-label>
            <q-avatar>
              <q-img :src="friend.photoURL" alt="avatar" />
              <q-icon name="person" />
            </q-avatar>
          </q-item-label>
          <q-item-label>{{ friend.name }}</q-item-label>
        </q-item-section> -->
        <q-table grid :rows="this.friends" :columns="columns" row-key="name" :filter="filter">
          <template v-slot:top-right>
            <q-input borderless dense debounce="300" v-model="filter" placeholder="Search">
              <template v-slot:append>
                <q-icon name="search" />
              </template>
            </q-input>
          </template>
          <template v-slot:item="props">
            <q-avatar>
              <q-img :src="props.row.photoURL" alt="avatar" />
              <q-icon name="person" />
            </q-avatar>
            <strong>{{ props.row.name }}</strong>
          </template>
        </q-table>
      </q-card-section>
    </q-card>
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
