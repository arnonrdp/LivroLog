<template>
  <q-page padding :style-fn="myTweak">
    <q-input v-model="filter" color="primary" dense debounce="300" flat :label="$t('friends.search-for-people')">
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
    </q-input>
    <q-table
      :rows="users"
      :columns="columns"
      :filter="filter"
      :rows-per-page-options="[10]"
      row-key="id"
      :loading="loading"
      :no-results-label="$t('friends.no-one-found')"
      flat
      hide-header
      separator="none"
      class="q-mt-md bg-transparent"
    >
      <template v-slot:body="props">
        <q-tr :props="props">
          <q-td key="id" :props="props" auto-width>
            <router-link :to="props.row.username">
              <q-avatar>
                <q-img v-if="props.row.photoURL" :src="props.row.photoURL" alt="avatar" />
                <q-icon v-else size="md" name="person" />
              </q-avatar>
            </router-link>
          </q-td>
          <q-td key="displayName" :props="props">
            <router-link :to="props.row.username">
              <strong>{{ props.row.displayName }}</strong>
              <span class="block">@{{ props.row.username }}</span>
            </router-link>
          </q-td>
          <q-td key="username" :props="props" auto-width>
            <q-chip
              v-if="getMyProfile.uid !== props.row.id"
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
          </q-td>
        </q-tr>
      </template>
      <template v-slot:loading>
        <q-inner-loading showing color="primary" />
      </template>
    </q-table>
  </q-page>
</template>

<script>
import { useMeta } from 'quasar';
import { useI18n } from 'vue-i18n';
import { mapGetters } from "vuex";

export default {
  setup() {
    const { t } = useI18n();
    useMeta({
      title: `Livrero | ${t("menu.people")}`,
      meta: {
        ogTitle: { name: "og:title", content: `Livrero | ${t("menu.people")}` },
        twitterTitle: { name: "twitter:title", content: `Livrero | ${t("menu.people")}` },
      },
    });
  },
  data: () => ({
    columns: [
      { name: "id", field: "id" },
      { name: "displayName", field: "displayName", align: "left" },
      { name: "username", field: "username" },
    ],
    filter: "",
    friends: [],
    loading: false,
    offset: 115,
    users: [],
  }),
  computed: {
    ...mapGetters(["getUsers", "getMyProfile"]),
  },
  mounted() {
    this.loadUsers();
  },
  methods: {
    myTweak() {
      return { minHeight: `calc(100vh - ${this.offset}px)` };
    },
    async loadUsers() {
      this.loading = true;
      await this.$store.dispatch("queryDBUsers");
      this.users = this.getUsers;
      this.loading = false;
    },
  },
};
</script>

<style scoped>
.q-page {
  margin: 0 auto;
  max-width: 32rem;
}
button {
  height: 1.6rem;
}
</style>
