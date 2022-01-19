<template>
  <q-page padding :style-fn="myTweek" class="non-selectable">
    <Shelf :shelfName="displayName" :books="books" @emitRemoveID="removeBook" />
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";
import { mapGetters } from "vuex";

export default {
  components: { Shelf },
  data: () => ({
    books: [],
    displayName: "",
    offset: 115,
  }),
  computed: {
    ...mapGetters(["getMyDisplayName", "getMyBooks"]),
  },
  async mounted() {
    this.displayName = this.getMyDisplayName;

    this.$store
      .dispatch("compareMyModifiedAt")
      .then(async (equals) => {
        if (!equals | !this.getMyBooks.length) await this.$store.dispatch("queryDBMyBooks");
        this.books = this.getMyBooks;
      })
      .catch((err) => console.error("err: ", err));
  },
  methods: {
    myTweek() {
      return { minHeight: `calc(100vh - ${this.offset}px)` };
    },
    removeBook(id) {
      this.$store
        .dispatch("removeBook", id)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("book.removed-success") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("book.removed-error") }));
    },
  },
};
</script>
