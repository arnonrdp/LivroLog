<template>
  <q-page class="q-mx-sm non-selectable">
    <Shelf :shelfName="username" :books="books" @emitID="removeBook" />
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";
import { mapGetters } from "vuex";

export default {
  components: { Shelf },
  data: () => ({
    books: [],
    username: "",
  }),
  computed: {
    ...mapGetters(["getMyID", "getMyShelfName", "getMyBooks"]),
  },
  async mounted() {
    this.username = this.getMyShelfName;

    this.$store
      .dispatch("compareMyModifiedAt")
      .then(async (equals) => {
        if (!equals | !this.getMyBooks.length) await this.$store.dispatch("queryDBMyBooks");
        this.books = this.getMyBooks;
      })
      .catch((err) => console.error("err: ", err));
  },
  methods: {
    removeBook(id) {
      this.$store
        .dispatch("removeBook", id)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("book.removed-success") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("book.removed-error") }));
    },
  },
};
</script>
