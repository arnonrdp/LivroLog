<template>
  <q-page class="q-mx-sm non-selectable">
    <Shelf :shelfName="shelfName" :books="books" @emitID="removeBook" />
    <!-- TODO: Inserir ícone de filtro para ordenar os livros -->
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";
import { collection, getDocs } from "firebase/firestore";
import { mapGetters } from "vuex";
import { db } from "../firebase";

export default {
  name: "Home",
  title: "Livrero",
  components: { Shelf },
  data: () => ({ shelfName: "", books: [] }),
  computed: {
    ...mapGetters(["getUserProfile"]),
  },
  async mounted() {
    const user = this.getUserProfile;
    this.shelfName = user.shelfName;

    const querySnapshot = await getDocs(collection(db, "users", user.uid, "addedBooks"));

    if (querySnapshot.size === user.books?.length) this.books = user.books;
    else this.booksFromFirebase(querySnapshot);
  },
  methods: {
    booksFromFirebase(querySnapshot) {
      let userBooks = [];
      querySnapshot.forEach((doc) => userBooks.push(doc.data()));
      this.books = userBooks;
      // TODO: Verificar possibilidade de usar Dispatch (criar getBooks?)
      this.$store.commit("setUserBooks", userBooks);
    },
    removeBook(id) {
      this.$store.dispatch("removeBook", id)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("book.removed-success") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("book.removed-error") }));
    },
  },
};
</script>
