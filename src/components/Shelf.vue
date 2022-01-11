<template>
  <div class="flex items-center justify-between">
    <h1 class="text-h5 text-secondary text-left q-my-none">{{ $t("book.bookcase", { name: shelfName }) }}</h1>
    <q-btn-dropdown flat icon="filter_list" size="md">
      <q-list class="non-selectable">
        <q-item clickable v-for="(label, name) in bookLabels" :key="label" @click="sort(name, ascDesc)">
          <q-item-section>{{ label }}</q-item-section>
          <q-item-section avatar>
            <q-icon v-if="name === sortKey" size="xs" :name="ascDesc === 'asc' ? 'arrow_downward' : 'arrow_upward'" />
          </q-item-section>
        </q-item>
      </q-list>
    </q-btn-dropdown>
  </div>
  <section>
    <figure v-for="book in books" :key="book.id">
      <q-btn
        v-if="selfUser"
        round
        color="negative"
        icon="close"
        size="sm"
        :title="$t('book.remove')"
        @click.once="$emit('emitID', book.id)"
      />
      <Tooltip :label="book.title" position="is-bottom">
        <img :src="book.thumbnail" :alt="`Livro ${book.title}`" />
      </Tooltip>
    </figure>
  </section>
</template>

<script>
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import { mapGetters } from "vuex";

export default {
  name: "Shelf",
  components: { Tooltip },
  props: {
    shelfName: { type: String, required: true },
    books: { type: Array, required: true },
  },
  emits: ["emitID"],
  data: () => ({
    ascDesc: "asc",
    bookLabels: {},
    selfUser: false,
    sortKey: "",
  }),
  computed: {
    ...mapGetters(["getUserShelfName"]),
  },
  async mounted() {
    this.bookLabels = {
      authors: this.$t("book.order-by-author"),
      addedIn: this.$t("book.order-by-date"),
      readIn: this.$t("book.order-by-read"),
      title: this.$t("book.order-by-title"),
    };

    this.selfUser = this.$route.params.username === undefined || this.$route.params.username === this.getUserShelfName;
  },
  methods: {
    sort(label, order) {
      this.sortKey = label;
      this.ascDesc = this.ascDesc === "asc" ? "desc" : "asc";
      const multiplier = order === "asc" ? 1 : -1;
      this.books.sort((a, b) => (a[label] < b[label] ? -1 * multiplier : 1 * multiplier));
    },
  },
};
</script>

<style scoped>
h1 {
  letter-spacing: 1px;
}

section {
  background-image: url("~@/assets/shelfleft.png"), url("~@/assets/shelfright.png"), url("~@/assets/shelfcenter.png");
  background-repeat: repeat-y, repeat-y, repeat;
  background-position: top left, top right, 240px 0;
  border-radius: 6px;
  display: flex;
  flex-flow: row wrap;
  justify-content: space-around;
  min-height: 302px;
  padding: 0 2rem 1rem;
}

section figure {
  align-items: flex-end;
  display: flex;
  height: 143.5px;
  margin: 0 1rem;
  max-width: 80px;
  position: relative;
}

figure button {
  opacity: 0;
  position: absolute;
  right: -1rem;
  top: 0.5rem;
  visibility: hidden;
  z-index: 1;
}

figure button:hover,
figure:hover button {
  opacity: 1;
  transition: 0.5s;
  visibility: visible;
}

img {
  height: 115px;
}
</style>
