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
    ...mapGetters(["getUsers", "getUserBooks"]),
  },
  async mounted() {
    this.username = this.$route.params.username;

    for (let user of this.getUsers) {
      if (user.shelfName === this.username) {
        this.uid = user.id;
        break;
      }
    }

    // TODO: Só consultar o banco de dados se o modifiedAt for diferente do que está no banco de dados
    await this.$store.dispatch("queryDBUserBooks", this.uid);

    this.books = this.getUserBooks(this.uid);
    console.log("this.books: ", this.books);
  },
};
</script>
