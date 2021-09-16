<template>
  <Header />
  <form action="#" @submit.prevent="submit">
    <Input
      v-model="seek"
      type="text"
      :label="$t('addlabel')"
    >
      <Button :text="$t('search')" @click="search" />
    </Input>
  </form>
  <div id="results">
    <figure v-for="(book, index) in books" :key="index">
      <Button
        text="+"
        @click="add(book.id, book.title, book.authors, book.thumbnail)"
      />
      <a><img :src="book.thumbnail" alt=""/></a>
      <figcaption>{{ book.title }}</figcaption>
      <figcaption id="authors">
        <span v-for="(author, i) in book.authors" :key="i">
          <span>{{ author }}</span>
          <span v-if="i + 1 < book.authors.length">, </span>
        </span>
      </figcaption>
    </figure>
  </div>
</template>

<script>
import { getAuth } from "firebase/auth";
import {
  getFirestore,
  doc,
  setDoc,
  arrayUnion,
  updateDoc,
  runTransaction,
} from "firebase/firestore";

import Header from "@/components/TheHeader.vue";
import Input from "@/components/BaseInput.vue";
import Button from "@/components/BaseButton.vue";

export default {
  name: "Add",
  components: { Header, Input, Button },
  data() {
    return {
      seek: "",
      results: "",
      books: {},
      noCover: "",
      unknown: ["Unknown"],
    };
  },
  methods: {
    search() {
      this.books = {};
      this.noCover = require("../assets/no_cover.jpg");
      // TODO: CHAMAR A API EM PRODUÇÃO => &key=${API}
      const API = "AIzaSyAJGXLBDW269OHGuSblb0FTg80EmdLLdBQ";
      fetch(
        `https://www.googleapis.com/books/v1/volumes?q=${this.seek}&maxResults=40&printType=books`
      )
        .then((response) => response.json())
        .then((data) => {
          this.books = data.items.map((item) => ({
            id: item.id,
            title: item.volumeInfo.title,
            authors: item.volumeInfo.authors ?? this.unknown,
            thumbnail: item.volumeInfo.imageLinks?.thumbnail ?? this.noCover,
          }));
          // TODO: TELA DE LOADING ENQUANTO CARREGA A LISTA
          // console.table(this.books);
        });
    },
    async add(bookID, title, authors, thumbnail) {
      const auth = getAuth();
      const db = getFirestore();
      const userID = auth.currentUser.uid;
      const booksRef = doc(db, "books", bookID);

      try {
        await runTransaction(db, async (transaction) => {
          const sfDoc = await transaction.get(booksRef);
          if (!sfDoc.exists()) {
            setDoc(doc(db, "books", bookID), {
              title: title,
              authors: authors,
              thumbnail: thumbnail,
              readers: arrayUnion(userID),
            });
          } else {
            await updateDoc(booksRef, {
              readers: arrayUnion(userID),
            });
          }
        });
      } catch (e) {
        console.log("ERRO: ", e);
      } finally {
        await setDoc(doc(db, "users", userID, "addedBooks", bookID), {
          bookRef: booksRef,
          addedIn: new Date(),
          readIn: "August",
        });
      }
    },
  },
};
</script>

<style scoped>
form {
  width: 100%;
}

form input {
  overflow: visible;
  outline: 0;
  width: 70%;
  padding: 10px;
  border-radius: 18px;
  background-color: #dee3e6;
  background-clip: padding-box;
  border: 0.5px solid #d1d9e6;
  box-shadow: var(--low-shadow);
}

form button {
  margin: 0;
  position: absolute;
  right: 9%;
  top: -1px;
}

input:focus ~ button {
  right: 6%;
}

#results {
  display: flex;
  flex-flow: row wrap;
  justify-content: center;
  align-items: baseline;
}

figure {
  padding-top: 5px;
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

#results img {
  width: 8rem;
}

figcaption {
  max-width: 8rem;
}

#authors {
  font-size: 12px;
  font-weight: bold;
}
</style>
