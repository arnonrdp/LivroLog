<template>
  <q-page class="q-mx-sm non-selectable">
    <Shelf :shelfName="getUserShelfName" :books="books" @emitID="removeBook" />
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";
import { doc, getDoc } from "firebase/firestore";
import { mapActions, mapGetters } from "vuex";
import { db } from "../firebase";

export default {
  name: "Home",
  title: "Livrero",
  components: { Shelf },
  data: () => ({ shelfName: "", books: [], modifiedAtDB: null }),
  computed: {
    ...mapGetters(["getUserID", "getBooks", "getUserShelfName", "getModifiedAt"]),
    ...mapActions(["queryBooksFromDB"]),
  },
  async mounted() {
    await this.queryModifiedAt();

    // TODO: Testar adição de livro pelo celular e verificar se o livro aparece na web
    if ((this.getUserID && this.getBooks.length === 0) || this.getModifiedAt !== this.modifiedAtDB) {
      await this.queryBooksFromDB;
    }

    this.books = this.getBooks;
  },
  methods: {
    removeBook(id) {
      this.$store
        .dispatch("removeBook", id)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("book.removed-success") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("book.removed-error") }));
    },
    async queryModifiedAt() {
      await getDoc(doc(db, "users", this.getUserID))
        .then((doc) => {
          this.modifiedAtDB = doc.data().modifiedAt;
          this.$store.commit("setModifiedAt", this.modifiedAtDB);
        })
        .catch((error) => console.error(error));
    },
  },
};
</script>
