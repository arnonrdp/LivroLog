<template>
  <main>
    <h1>{{ $t("shelf", { name: shelfName }) }}</h1>
    <section>
      <figure v-for="book in books" :key="book.id">
        <Tooltip :label="book.title" position="is-bottom">
          <img :src="book.thumbnail" :alt="`Livro ${book.title}`" />
        </Tooltip>
      </figure>
    </section>
  </main>
</template>

<script>
import { auth, db } from "@/firebase";
import { collection, doc, getDoc, getDocs, query } from "firebase/firestore";
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import { mapGetters } from "vuex";

export default {
  name: "Shelf",
  components: { Tooltip },
  data: () => ({ shelfName: "", books: [] }),
  computed: {
    ...mapGetters(["getUserProfile"]),
  },
  async mounted() {
    const userID = auth.currentUser.uid;
    const userRef = doc(db, "users", userID);
    const userSnap = await getDoc(userRef);

    this.shelfName = userSnap.data().shelfName;

    const storageKey = `Livrero:${this.shelfName}`;
    const storageValue = [];

    if (localStorage.getItem(storageKey)) {
      try {
        this.storageValue = JSON.parse(localStorage.getItem(storageKey));
        this.books = this.storageValue;
      } catch (error) {
        localStorage.removeItem(storageKey);
      }
    } else {
      const userBooksRef = query(collection(db, "users", userID, "addedBooks"));
      const querySnapshot = await getDocs(userBooksRef);
      querySnapshot.forEach((doc) => {
        this.books.push({
          id: doc.id,
          addedIn: doc.data().addedIn,
          readIn: doc.data().readIn,
        });
      });

      this.books.map(async (book) => {
        const booksRef = doc(db, "books", book.id);
        const bookSnap = await getDoc(booksRef);

        book.authors = bookSnap.data().authors;
        book.title = bookSnap.data().title;
        book.thumbnail = bookSnap.data().thumbnail;

        storageValue.push(book);
        const parsed = JSON.stringify(storageValue);
        localStorage.setItem(storageKey, parsed);
      });
    }
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

img {
  height: 115px;
}
</style>
