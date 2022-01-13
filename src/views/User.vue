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

    // TODO: Verificar se é possível utilizar .filter() ou .find() aqui
    for (let user of this.getUsers) {
      if (user.shelfName === this.username) {
        this.uid = user.id;
        break;
      }
    }

    this.$store.dispatch("compareModifiedAt", this.uid).then(async (equals) => {
      if (!equals | !this.getUserBooks(this.uid)) await this.$store.dispatch("queryDBUserBooks", this.uid);
      this.books = this.getUserBooks(this.uid);
    });
  },
};
</script>
