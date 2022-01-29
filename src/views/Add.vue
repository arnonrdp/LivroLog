<template>
  <q-page padding :style-fn="myTweak">
    <q-input v-model="seek" type="text" :label="$t('book.addlabel')" @keyup.enter="search" dense>
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
      <template v-slot:append>
        <q-icon name="close" @click="clearSearch" class="cursor-pointer" />
      </template>
    </q-input>
    <Loading v-show="loading" />
    <div id="results">
      <figure v-for="(book, index) in books" :key="index">
        <q-btn round color="primary" icon="add" @click.once="addBook(book)" />
        <a><img :src="book.thumbnail" alt="" /></a>
        <figcaption>{{ book.title }}</figcaption>
        <figcaption id="authors">
          <span v-for="(author, i) in book.authors" :key="i">
            <span class="text-body2 text-weight-bold">{{ author }}</span>
            <span v-if="i + 1 < book.authors.length">, </span>
          </span>
        </figcaption>
      </figure>
    </div>
  </q-page>
</template>

<script>
import Loading from "@/components/Loading.vue";
import axios from "axios";

export default {
  name: "Add",
  components: { Loading },
  data() {
    return {
      books: [],
      loading: false,
      offset: 115,
      results: "",
      seek: "",
    };
  },
  methods: {
    myTweak() {
      return { minHeight: `calc(100vh - ${this.offset}px)` };
    },
    
    clearSearch() {
      this.seek = "";
      this.books = [];
    },

    search() {
      this.loading = true;
      this.books = [];
      axios
        .get(`https://www.googleapis.com/books/v1/volumes?q=${this.seek}&maxResults=40&printType=books`)
        .then((response) => {
          response.data.items.map((item) =>
            this.books.push({
              id: item.id,
              title: item.volumeInfo.title || "",
              authors: item.volumeInfo.authors || [this.$t("book.unknown-author")],
              ISBN: item.volumeInfo.industryIdentifiers?.[0].identifier || item.id,
              thumbnail:
                item.volumeInfo.imageLinks?.thumbnail.replace("http", "https") || require("../assets/no_cover.jpg"),
            }),
          );
        })
        .catch((error) => console.error(error))
        .finally(() => (this.loading = false));
    },

    addBook(book) {
      book = { ...book, addedIn: Date.now(), readIn: "" };
      this.$store
        .dispatch("addBook", book)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("book.added-to-shelf") }))
        .catch((error) => this.$q.notify({ icon: "error", message: this.errorMessages()[error] }));
    },

    errorMessages() {
      return {
        book_already_exists: this.$t("book.already-exists"),
      };
    },
  },
};
</script>

<style scoped>
.q-input {
  margin: auto;
  max-width: 32rem;
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
  z-index: 1;
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
</style>
