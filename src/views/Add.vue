<template>
  <Header />
  <form action="#" @submit.prevent="submit">
    <Input
      v-model="seek"
      type="text"
      placeholder="Pesquise por título, autor, editora, lançamento, ISBN..."
    />
    <Button text="Buscar" @click="search" />
  </form>
  <div id="results">
    <figure v-for="(book, index) in books" :key="index">
      <a href="#">+</a>
      <a><img :src="book.thumbnail" alt=""/></a>
      <figcaption>{{ book.title }}</figcaption>
      <figcaption id="authors">{{ book.authors }}</figcaption>
    </figure>
  </div>
</template>

<script>
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
    };
  },
  methods: {
    search() {
      this.books = {};
      this.noCover = require("../assets/no_cover.jpg");
      // TODO: CHAMAR A API EM PRODUÇÃO => &key=${API}
      const API = "AIzaSyAJGXLBDW269OHGuSblb0FTg80EmdLLdBQ";
      fetch(
        `https://www.googleapis.com/books/v1/volumes?q=${this.seek}&maxResults=10&printType=books`
      )
        .then((response) => response.json())
        .then((data) => {
          this.books = data.items.map((item) => ({
            title: item.volumeInfo.title,
            authors: item.volumeInfo.authors,
            thumbnail: item.volumeInfo.imageLinks?.thumbnail ?? this.noCover,
          }));
          // TODO: TELA DE LOADING ENQUANTO CARREGA A LISTA
          console.table(this.books);
        });
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

#results {
  display: flex;
  flex-flow: row wrap;
  justify-content: center;
  align-items: flex-end;
}

#results img {
  width: 8rem;
}

#authors {
  font-size: 12px;
  font-weight: bold;
}
</style>
