<template>
  <q-page padding :style-fn="myTweek" class="non-selectable">
    <Shelf :shelfName="username" :books="books" @emitAddID="addBook" />
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";
import { mapGetters } from "vuex";

export default {
  components: { Shelf },
  data: () => ({
    books: [],
    offset: 115,
    uid: "",
    username: "",
  }),
  computed: {
    ...mapGetters(["getUsers", "getUserBooks"]),
  },
  async mounted() {
    this.username = this.$route.params.username;

    this.uid = this.getUsers.find((user) => user.shelfName === this.username).id;

    this.$store.dispatch("compareUserModifiedAt", this.uid).then(async (equals) => {
      if (!equals | !this.getUserBooks(this.uid)) await this.$store.dispatch("queryDBUserBooks", this.uid);
      this.books = this.getUserBooks(this.uid);
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
