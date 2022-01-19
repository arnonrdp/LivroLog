<template>
  <q-page padding :style-fn="myTweek" class="non-selectable">
    <Shelf :shelfName="displayName" :books="books" @emitAddID="addBook" />
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";
import store from "@/store";
import { mapGetters } from "vuex";

export default {
  components: { Shelf },
  data: () => ({
    books: [],
    displayName: "",
    offset: 115,
  }),
  beforeRouteEnter(to, from, next) {
    store.getters.getUsers.some((user) => user.username === to.params.username) ? next() : next("/");
  },
  computed: {
    ...mapGetters(["getUsers", "getUserBooks"]),
  },
  async mounted() {
    const { displayName, id } = this.getUsers.find((user) => user.username === this.$route.params.username);
    this.displayName = displayName;

    this.$store.dispatch("compareUserModifiedAt", id).then(async (equals) => {
      if (!equals | !this.getUserBooks(id)) await this.$store.dispatch("queryDBUserBooks", id);
      this.books = this.getUserBooks(id);
    });
  },
  methods: {
    myTweek() {
      return { minHeight: `calc(100vh - ${this.offset}px)` };
    },
    addBook(book) {
      book = { ...book, addedIn: Date.now(), readIn: "" };
      this.$store
        .dispatch("addBook", book)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("book.added-to-shelf") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("book.already-exists") }));
    },
  },
};
</script>
