<template>
  <Header />
  <form action="#" @submit.prevent="submit" class="q-pa-md">
    <q-input v-model="seek" type="text" :label="$t('book.addlabel')" @keyup.enter="search" dense>
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
      <template v-slot:append>
        <q-icon
          name="close"
          @click="
            seek = '';
            books = '';
          "
          class="cursor-pointer"
        />
      </template>
    </q-input>
  </form>
  <Loading v-show="loading" />
  <div id="results">
    <figure v-for="(book, index) in books" :key="index">
      <q-btn round color="primary" icon="add" @click="addBook(book)" />
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
import Button from "@/components/BaseButton.vue";
import Input from "@/components/BaseInput.vue";
import Loading from "@/components/Loading.vue";
import Header from "@/components/TheHeader.vue";
import axios from "axios";

export default {
  name: "Add",
  components: { Header, Input, Button, Loading },
  data() {
    return {
      seek: "",
      results: "",
      shelfName: "",
      books: [],
      loading: false,
    };
  },
  methods: {
    search() {
      this.loading = true;
      this.books = [];
      axios
        // TODO: Chamar a API em produção => &key=${process.env.GOOGLE_BOOKS_API_KEY}
        .get(`https://www.googleapis.com/books/v1/volumes?q=${this.seek}&maxResults=40&printType=books`)
        .then((response) => {
          response.data.items.map((item) =>
            this.books.push({
              id: item.id,
              title: item.volumeInfo.title,
              authors: item.volumeInfo.authors || [this.$t("book.unknown-author")],
              ISBN: item.volumeInfo.industryIdentifiers?.[0].identifier ?? item.id,
              thumbnail:
                item.volumeInfo.imageLinks?.thumbnail.replace("http", "https") ?? require("../assets/no_cover.jpg"),
            }),
          );
        })
        .catch((error) => console.error(error))
        .finally(() => (this.loading = false));
    },
    addBook(book) {
      book = { ...book };
      this.$store.dispatch("addBook", book);
      this.showNotification();
    },
    showNotification() {
      const message = this.$t("book.added-to-shelf");
      const position = "top-right";
      this.$q.notify({ message, position });
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

#results {
  align-items: baseline;
  display: flex;
  flex-flow: row wrap;
  justify-content: center;
}

figure {
  padding-top: 5px;
  position: relative;
}

figure button {
  opacity: 0;
  position: absolute;
  right: -1.5rem;
  top: -1rem;
  visibility: hidden;
}

figure:hover button,
figure button:hover {
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
