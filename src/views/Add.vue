<template>
  <Header />
  <form action="#" @submit.prevent="submit">
    <Input v-model="seek" type="text" :label="$t('book.addlabel')" @keyup.enter="search" />
  </form>
  <Loading v-show="loading" />
  <div id="results">
    <figure v-for="(book, index) in books" :key="index">
      <Button text="+" @click="addBook(book)" />
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
      books: [],
      noCover: "",
      storageValue: [],
      loading: false,
    };
  },
  methods: {
    search() {
      this.loading = true;
      axios
        // TODO: CHAMAR A API EM PRODUÇÃO => &key=${API}
        // const API = "AIzaSyAJGXLBDW269OHGuSblb0FTg80EmdLLdBQ";
        .get(`https://www.googleapis.com/books/v1/volumes?q=${this.seek}&maxResults=40&printType=books`)
        .then((response) => {
          response.data.items.map((item) => this.books.push({
            id: item.id,
            title: item.volumeInfo.title,
            authors: item.volumeInfo.authors || [this.$t("book.unknown-author")],
            ISBN: item.volumeInfo.industryIdentifiers?.[0].identifier ?? item.id,
            thumbnail: item.volumeInfo.imageLinks?.thumbnail ?? require("../assets/no_cover.jpg"),
          }));
        })
        .catch((error) => console.error(error))
        .finally(() => (this.loading = false));
    },
    addBook(book) {
      book = { ...book };
      this.$store.dispatch("addBook", book);
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
