<template>
  <main>
    <h1>{{ $t("book.bookcase", { name: getUserProfile.shelfName }) }}</h1>
    <section>
      <figure v-for="book in books" :key="book.id">
        <Button text="–" @click="removeBook(book.id)" />
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
import { collection, doc, getDoc, getDocs, query } from "firebase/firestore";
import { mapGetters } from "vuex";
import axios from "axios";

export default {
  name: "Shelf",
  components: { Tooltip, Button },
  data: () => ({ shelfName: "", books: [] }),
  computed: {
    ...mapGetters(["getUserProfile"]),
  },
  async mounted() {
    // TODO: Vuex-Book-State
    const user = this.getUserProfile;
    const querySnapshot = await getDocs(query(collection(db, "users", user.uid, "addedBooks")));

    console.log("Livros no banco:", querySnapshot.size);
    console.log("Livros no storage:", this.getUserProfile.books?.length);

    if (querySnapshot.size !== this.getUserProfile.books?.length) {
      console.log("Entrou no IF");
      let userBooks = [];
      querySnapshot.forEach((book) =>
        userBooks.push({
          id: book.id,
          addedIn: book.data().addedIn,
          readIn: book.data().readIn,
        }),
      );
      //TODO: Fazer algo para evitar duplicação de livros
      this.searchByID(userBooks);
    } else {
      console.log("Entrou no ELSE");
      this.books = this.getUserProfile.books;
    }
    // Local Storage
    // const userID = this.getUserProfile.uid;
    // const storageKey = `Livrero:${userID}`;
    // const storageValue = [];

    // if (localStorage.getItem(storageKey)) {
    //   try {
    //     this.storageValue = JSON.parse(localStorage.getItem(storageKey));
    //     this.books = this.storageValue;
    //   } catch (error) {
    //     localStorage.removeItem(storageKey);
    //   }
    // } else {
    //   const userBooksRef = query(collection(db, "users", userID, "addedBooks"));
    //   const querySnapshot = await getDocs(userBooksRef);
    //   querySnapshot.forEach((doc) => {
    //     this.books.push({
    //       id: doc.id,
    //       addedIn: doc.data().addedIn,
    //       readIn: doc.data().readIn,
    //     });
    //   });

    //   this.books.map(async (book) => {
    //     const booksRef = doc(db, "books", book.id);
    //     const bookSnap = await getDoc(booksRef);

    //     book.authors = bookSnap.data().authors;
    //     book.title = bookSnap.data().title;
    //     book.thumbnail = bookSnap.data().thumbnail;

    //     storageValue.push(book);
    //     const parsed = JSON.stringify(storageValue);
    //     localStorage.setItem(storageKey, parsed);
    //   });
    // }
  },
  methods: {
    removeBook(id) {
      this.$store.dispatch("removeBook", id);
    },
    searchByID(array) {
      this.$store.commit("setLoading", true);
      array.map((arr) => {
        axios
          .get(`https://www.googleapis.com/books/v1/volumes/${arr.id}`)
          .then((response) => {
            this.books.push({
              id: response.data.id,
              title: response.data.volumeInfo.title,
              authors: response.data.volumeInfo.authors || [this.$t("book.unknown-author")],
              ISBN: response.data.volumeInfo.industryIdentifiers?.[0].identifier ?? response.data.id,
              thumbnail: response.data.volumeInfo.imageLinks?.thumbnail ?? require("../assets/no_cover.jpg"),
            });
          })
          .catch((error) => console.error(error))
          .finally(() => this.$store.commit("setLoading", false));
      });
      console.log(typeof this.books);
      this.$store.commit("setUserBooks", this.books);
      // return this.books;
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
  margin: -2.5rem 2.5rem;
  opacity: 0;
  position: absolute;
  visibility: hidden;
}

figure:hover button,
figure button:hover {
  font-weight: bolder;
  opacity: 1;
  transition: 0.5s;
  visibility: visible;
}

img {
  height: 115px;
}
</style>
