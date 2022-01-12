<template>
  <q-page class="q-mx-sm non-selectable">
    <Shelf :shelfName="username" :books="books" @emitID="removeBook" />
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";
import { mapActions, mapGetters } from "vuex";

export default {
  // TODO: Verificar a necessidade de 'name' e 'title' no export default
  // name: "Home",
  // title: "Livrero",
  components: { Shelf },
  data: () => ({
    username: "",
    books: [],
    modifiedAtDB: null,
  }),
  computed: {
    ...mapGetters(["getUserID", "getBooks", "getUserShelfName", "getModifiedAt"]),
    ...mapActions(["queryBooksFromDB"]),
  },
  async mounted() {
    this.username = this.getUserShelfName;

    // TODO: Testar adição de livro pelo celular e verificar se o livro aparece na web
    // if (this.getModifiedAt !== (await this.modifiedAtDB)) {
      console.log("Home está consultando o banco de dados");
      await this.queryBooksFromDB;
    // }

    this.books = this.getBooks;
  },
  methods: {
    removeBook(id) {
      this.$store
        .dispatch("removeBook", id)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("book.removed-success") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("book.removed-error") }));
    },
  },
};
</script>
