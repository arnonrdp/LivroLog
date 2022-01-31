<template>
  <q-page padding :style-fn="myTweek" class="non-selectable">
    <Shelf :shelfName="displayName" :books="books" @emitRemoveID="removeBook" />
  </q-page>
</template>

<script>
import Shelf from "@/components/Shelf.vue";
import { mapGetters } from "vuex";
import { useI18n } from 'vue-i18n';
import { useMeta } from 'quasar';

export default {
  components: { Shelf },
  setup() {
    const { t } = useI18n();
    useMeta({
      title: `Livrero | ${t("menu.home")}`,
      meta: {
        ogTitle: { name: "og:title", content: `Livrero | ${t("menu.home")}` },
        twitterTitle: { name: "twitter:title", content: `Livrero | ${t("menu.home")}` },
      },
    });
  },
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
