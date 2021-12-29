<template>
  <main>
    <h1>{{ $t("book.bookcase", { name: shelfName }) }}</h1>
    <section>
      <figure v-for="book in books" :key="book.id">
        <Button text="🗑" :title="$t('book.remove')" @click="removeBook(book.id)" />
        <Tooltip :label="book.title" position="is-bottom">
          <img :src="book.thumbnail" :alt="`Livro ${book.title}`" />
        </Tooltip>
      </figure>
    </section>
  </main>
</template>

<script>
import Button from "@/components/BaseButton.vue";
import { db } from "@/firebase";
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import axios from "axios";
import { collection, getDocs, query } from "firebase/firestore";
import { mapGetters } from "vuex";

export default {
  name: "Shelf",
  components: { Tooltip, Button },
  data: () => ({ shelfName: "", books: [] }),
  computed: {
    ...mapGetters(["getUserProfile"]),
  },
  async mounted() {
    this.shelfName = this.getUserProfile.shelfName;

    const user = this.getUserProfile;
    const querySnapshot = await getDocs(query(collection(db, "users", user.uid, "addedBooks")));

    if (querySnapshot.size !== user.books?.length) {
      console.log("Entrou no IF");
      this.queryUserBooks(querySnapshot);
    }
    this.getBooks();
  },
  methods: {
    getBooks() {
      this.books = this.getUserProfile.books;
    },
    removeBook(id) {
      this.$store.dispatch("removeBook", id);
    },
    queryUserBooks(querySnapshot) {
      let userBooks = [];
      querySnapshot.forEach((book) =>
        userBooks.push({
          id: book.id,
          addedIn: book.data().addedIn,
          readIn: book.data().readIn,
        }),
      );
      this.$store.commit("setUserBooks", this.searchByID(userBooks));
    },
    searchByID(array) {
      this.$store.commit("setLoading", true);
      let books = [];
      array.map((arr) => {
        axios
          .get(`https://www.googleapis.com/books/v1/volumes/${arr.id}`)
          .then((response) => {
            const book = response.data.volumeInfo;
            books.push({
              id: response.data.id,
              title: book.title,
              authors: book.authors || [this.$t("book.unknown-author")],
              ISBN: book.industryIdentifiers?.[0].identifier ?? response.data.id,
              thumbnail: book.imageLinks?.thumbnail ?? require("../assets/no_cover.jpg"),
            });
          })
          .catch((error) => console.error(error))
          .finally(() => this.$store.commit("setLoading", false));
      });
      return books;
    },
  },
};
</script>

<style scoped>
main {
  margin: 0 10px;
}

h1 {
  color: #491f00;
  font-size: 1.5rem;
  letter-spacing: 1px;
  margin: 0;
  text-align: left;
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
  padding: 0 50px 15px;
}

section figure {
  align-items: flex-end;
  display: flex;
  height: 143.5px;
  margin: 0 30px;
  max-width: 80px;
  position: relative;
}

figure button {
  border-radius: 50%;
  opacity: 0;
  padding: 0.3rem 0.6rem;
  position: absolute;
  right: -15px;
  top: 10px;
  visibility: hidden;
  z-index: 1;
}

figure:hover button {
  opacity: 1;
  transition: 0.5s;
  visibility: visible;
}

figure button:hover {
  background-color: #ff0e0e;
}

img {
  height: 115px;
}
</style>
