<template>
  <Header />
  <form action="#" @submit.prevent="submit">
    <Input v-model="seek" type="text" :label="$t('addlabel')" @keyup.enter="search" />
  </form>
  <Loading v-show="loading" />
  <div id="results">
    <figure v-for="(book, index) in books" :key="index">
      <Button text="+" @click="add(book.id, book.title, book.authors, book.thumbnail)" />
      <a><img :src="book.thumbnail" alt="" /></a>
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
import { auth, db } from "@/firebase";
import { doc, getDoc, setDoc, arrayUnion, updateDoc, runTransaction } from "firebase/firestore";
import axios from "axios";
import Header from "@/components/TheHeader.vue";
import Input from "@/components/BaseInput.vue";
import Button from "@/components/BaseButton.vue";
import Loading from "@/components/Loading.vue";

export default {
  name: "Add",
  components: { Header, Input, Button, Loading },
  data() {
    return {
      seek: "",
      results: "",
      shelfName: "",
      books: {},
      noCover: "",
      unknown: ["Unknown"],
      storageValue: [],
      loading: false,
    };
  },
  async mounted() {
    const userID = auth.currentUser.uid;
    const userRef = doc(db, "users", userID);
    const userSnap = await getDoc(userRef);

    this.shelfName = userSnap.data().shelfName;

    const storageKey = `Livrero:${this.shelfName}`;

    if (localStorage.getItem(storageKey)) {
      try {
        this.storageValue = JSON.parse(localStorage.getItem(storageKey));
      } catch (error) {
        localStorage.removeItem(storageKey);
      }
    }
  },
  methods: {
    search() {
      this.books = {};
      this.noCover = require("../assets/no_cover.jpg");
      this.loading = true;
      axios
        .get(
          // TODO: CHAMAR A API EM PRODUÇÃO => &key=${API}
          // const API = "AIzaSyAJGXLBDW269OHGuSblb0FTg80EmdLLdBQ";
          `https://www.googleapis.com/books/v1/volumes?q=${this.seek}&maxResults=40&printType=books`,
        )
        .then((response) => {
          this.books = response.data.items.map((item) => ({
            id: item.id,
            title: item.volumeInfo.title,
            authors: item.volumeInfo.authors || this.unknown,
            thumbnail: item.volumeInfo.imageLinks?.thumbnail ?? this.noCover,
          }));
        })
        .catch((error) => console.error(error))
        .finally(() => (this.loading = false));
    },
    async add(bookID, title, authors, thumbnail) {
      const tempStorage = {};
      tempStorage.id = bookID;
      tempStorage.addedIn = new Date();
      tempStorage.readIn = "";
      tempStorage.authors = authors;
      tempStorage.thumbnail = thumbnail;
      tempStorage.title = title;

      this.storageValue.push(tempStorage);
      const storageKey = `Livrero:${this.shelfName}`;
      const parsed = JSON.stringify(this.storageValue);
      localStorage.setItem(storageKey, parsed);

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
      } catch (err) {
        console.error("ERRO: ", err);
      } finally {
        await setDoc(doc(db, "users", userID, "addedBooks", bookID), {
          bookRef: booksRef,
          addedIn: new Date(),
          readIn: "",
        });
      }
    },
  },
};
</script>

<style scoped>
form {
  margin: auto;
  width: 50%;
}

@media screen and (max-width: 840px) {
  form {
    width: 100%;
  }
}

form input {
  background-clip: padding-box;
  background-color: #dee3e6;
  border: 0.5px solid #d1d9e6;
  border-radius: 18px;
  box-shadow: var(--low-shadow);
  outline: 0;
  overflow: visible;
  padding: 10px;
  width: 70%;
}

/* form button {
    margin: 0;
    position: absolute;
    right: 9%;
    top: -1px;
  }

  input:focus ~ button {
    right: 6%;
  } */

#results {
  align-items: baseline;
  display: flex;
  flex-flow: row wrap;
  justify-content: center;
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
