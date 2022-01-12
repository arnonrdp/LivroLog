<template>
  <q-page class="q-mx-sm non-selectable">
    <Shelf :shelfName="username" :books="books" />
  </q-page>
</template>

<script>
import { mapGetters } from "vuex";
import Shelf from "../components/Shelf.vue";

export default {
  components: { Shelf },
  data: () => ({
    books: [],
    uid: "",
    username: "",
  }),
  computed: {
    ...mapGetters(["getUsers", 'getUserBooks']),
  },
  async mounted() {
    this.username = this.$route.params.username;

    for (let user of this.getUsers) {
      if (user.shelfName === this.username) {
        this.uid = user.id;
        break;
      }
    }
    // TODO: Só chamar a action abaixo se o horário modificado no banco for diferente do horário do Vuex
    await this.$store.dispatch('queryBooksFromUser', this.uid);
    this.books = this.getUserBooks(this.uid);

  },
};
</script>
