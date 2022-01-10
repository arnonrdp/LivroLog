<template>
  <div class="flex items-center justify-between">
    <h1 class="text-h5 text-secondary text-left q-my-none">{{ $t("book.bookcase", { name: shelfName }) }}</h1>
    <q-btn-group flat>
      <q-btn icon="download" @click.prevent="shotPic" />
      <q-btn-dropdown icon="filter_list">
        <q-list class="non-selectable">
          <q-item clickable v-for="(label, name) in bookLabels" :key="label" @click="sort(name, ascDesc)">
            <q-item-section>{{ label }}</q-item-section>
            <q-item-section avatar>
              <q-icon v-if="name === sortKey" size="xs" :name="ascDesc === 'asc' ? 'arrow_downward' : 'arrow_upward'" />
            </q-item-section>
          </q-item>
        </q-list>
      </q-btn-dropdown>
    </q-btn-group>
  </div>
  <div ref="capture">
    <section>
      <figure v-for="book in books" :key="book.id">
        <q-btn
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
    <img :src="img" v-if="img" />
  </div>
</template>

<script>
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import domtoimage from "dom-to-image-more";

export default {
  name: "Shelf",
  components: { Tooltip, domtoimage },
  props: {
    shelfName: { type: String, required: true },
    books: { type: Array, required: true },
  },
  data: () => ({
    bookLabels: {},
    ascDesc: "asc",
    sortKey: "",
    config: {
      value: "",
    },
    img: "",
  }),
  emits: ["emitID"],
  mounted() {
    this.bookLabels = {
      authors: this.$t("book.order-by-author"),
      addedIn: this.$t("book.order-by-date"),
      readIn: this.$t("book.order-by-read"),
      title: this.$t("book.order-by-title"),
    };
    this.config.value = "https://www.google.com/";
  },
  methods: {
    sort(label, order) {
      const multiplier = order === "asc" ? 1 : -1;
      this.sortKey = label;
      this.ascDesc = this.ascDesc === "asc" ? "desc" : "asc";
      this.books.sort((a, b) => {
        if (a[label] < b[label]) return -1 * multiplier;
        if (a[label] > b[label]) return 1 * multiplier;
        return 0;
      });
    },
    shotPic() {
      const capture = this.$refs.capture;
      domtoimage
        .toPng(capture)
        .then((dataUrl) => this.setImage(dataUrl))
        .catch((error) => console.error("oops, something went wrong!", error));
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
  min-height: 285px;
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
