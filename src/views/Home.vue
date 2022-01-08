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
    ...mapGetters(["getUserID", "getBooks", "getUserShelfName"]),
  },
  async mounted() {
    const userID = this.getUserID;
    const userBooks = this.getBooks;
    this.shelfName = this.getUserShelfName;

    // TODO: Verificar necessidade de criar função para armazenar o horário da última atualização e, a partir desse horário, consultar ou no Firestore ou no LocalStorage
    const querySnapshot = await getDocs(collection(db, "users", userID, "addedBooks"));

    if (querySnapshot.size === userBooks?.length) this.books = userBooks;
    else this.booksFromFirebase(querySnapshot);
  },
  methods: {
    booksFromFirebase(querySnapshot) {
      let vuexBooks = [];
      querySnapshot.forEach((doc) => vuexBooks.push(doc.data()));
      this.books = vuexBooks;
      // TODO: Verificar possibilidade de usar Dispatch (criar getBooks?)
      this.$store.commit("setBooks", this.books);
    },
    removeBook(id) {
      this.$store.dispatch("removeBook", id)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("book.removed-success") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("book.removed-error") }));
    },
  },
};
</script>
