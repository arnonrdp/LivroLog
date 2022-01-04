<template>
  <div>
    <h1 class="text-h5 text-secondary text-left q-my-none">{{ $t("book.bookcase", { name: shelfName }) }}</h1>
    <section>
      <figure v-for="book in books" :key="book.id">
        <q-btn round color="negative" icon="close" size="sm" :title="$t('book.remove')" @click="removeBook(book.id)" />
        <Tooltip :label="book.title" position="is-bottom">
          <img :src="book.thumbnail" :alt="`Livro ${book.title}`" />
        </Tooltip>
      </figure>
    </section>
  </div>
</template>

<script>
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import { collection, getDocs } from "firebase/firestore";
import { mapGetters } from "vuex";
import { db } from "../firebase";

export default {
  name: "Shelf",
  components: { Tooltip },
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
    removeBook(id) {
      this.$store.dispatch("removeBook", id);
    },
    booksFromFirebase(querySnapshot) {
      let userBooks = [];
      querySnapshot.forEach((doc) => {
        userBooks.push({ id: doc.id, ...doc.data() });
      });
      this.books = userBooks;
      this.$store.commit("setUserBooks", userBooks);
    },
  },
};
</script>

<style scoped>
h1 {
  letter-spacing: 1px;
}

section {
  background-image: url("~@/assets/shelfleft.png"), url("~@/assets/shelfright.png"), url("~@/assets/shelfcenter.png");
  background-repeat: repeat-y, repeat-y, repeat;
  background-position: top left, top right, 240px 0;
  border-radius: 6px;
  display: flex;
  flex-flow: row wrap;
  justify-content: space-around;
  min-height: 285px;
  padding: 0 2rem 1rem;
}

section figure {
  align-items: flex-end;
  display: flex;
  height: 143.5px;
  margin: 0 1rem;
  max-width: 80px;
  position: relative;
}

figure button {
  opacity: 0;
  position: absolute;
  right: -1rem;
  top: 0.5rem;
  visibility: hidden;
  z-index: 1;
}

figure button:hover,
figure:hover button {
  opacity: 1;
  transition: 0.5s;
  visibility: visible;
}

img {
  height: 115px;
}
</style>
