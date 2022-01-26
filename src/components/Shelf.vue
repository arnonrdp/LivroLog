<template>
  <div class="flex items-center justify-between">
    <h1 class="text-h5 text-secondary text-left q-my-none">{{ $t("book.bookcase", { name: shelfName }) }}</h1>
    <q-btn-dropdown flat icon="filter_list" size="md">
      <q-list class="non-selectable">
        <q-item clickable v-for="(label, name) in bookLabels" :key="label" @click="sort(books, name, ascDesc)">
          <q-item-section>{{ label }}</q-item-section>
          <q-item-section avatar>
            <q-icon v-if="name === sortKey" size="xs" :name="ascDesc === 'asc' ? 'arrow_downward' : 'arrow_upward'" />
          </q-item-section>
        </q-item>
      </q-list>
    </q-btn-dropdown>
  </div>
  <button type="button" @click="shotPic">screenshot</button>
  <section id="my-node">
    <!-- TODO: Adicionar a possibilidade de filtrar os livros -->
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
      <q-btn
        v-else
        round
        color="primary"
        icon="add"
        size="sm"
        :title="$t('book.add')"
        @click.once="$emit('emitAddID', book)"
      />
      <img :src="book.thumbnail" :alt="`Livro ${book.title}`" />
      <!-- TODO: Manter tooltip ativa no mobile ao clicar na imagem do livro -->
      <q-tooltip anchor="bottom middle" self="center middle" class="bg-black">{{ book.title }}</q-tooltip>
    </figure>
  </section>
</template>

<script>
import html2canvas from "html2canvas";
import domtoimage from "dom-to-image-more";
import { mapGetters } from "vuex";

export default {
  name: "Shelf",
  props: {
    books: { type: Array, required: true },
    shelfName: { type: String, required: true },
  },
  emits: ["emitRemoveID", "emitAddID"],
  data: () => ({
    ascDesc: "asc",
    bookLabels: {},
    selfUser: false,
    sortKey: "",
  }),
  computed: {
    ...mapGetters(["getMyUsername"]),
  },
  async mounted() {
    this.bookLabels = {
      authors: this.$t("book.order-by-author"),
      addedIn: this.$t("book.order-by-date"),
      readIn: this.$t("book.order-by-read"),
      title: this.$t("book.order-by-title"),
    };

    this.selfUser = !this.$route.params.username || this.$route.params.username === this.getMyUsername;
  },
  methods: {
    shotPic() {
      domtoimage
        .toPng(document.getElementById("my-node"), { quality: 0.95 })
        .then((dataUrl) => {
          let link = document.createElement("a");
          link.download = "my-image-name.jpeg";
          link.href = dataUrl;
          link.click();
        })
        .catch((error) => console.error(error));
    },

    sort(books, label, order) {
      this.sortKey = label;
      this.ascDesc = this.ascDesc === "asc" ? "desc" : "asc";
      const multiplier = order === "asc" ? 1 : -1;
      books.sort((a, b) => (a[label] > b[label] ? 1 : a[label] < b[label] ? -1 : 0) * multiplier);
    },
    download() {
      html2canvas(this.$refs["capture"], {
        allowTaint: true,
        proxy: "https://books.google.com/",
        // scale: (1920 * 2) / window.innerWidth,
        useCORS: true,
      })
        .then((canvas) => console.log("TODO: Executar download de: ", canvas.toDataURL("image/jpg"), "Livrero.jpg"))
        .catch((error) => console.error("ERROR SAVING IMAGE", error));
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
  padding: 0 3rem 1rem;
}

section figure {
  align-items: flex-end;
  display: flex;
  height: 143.5px;
  margin: 0 1.5rem;
  max-width: 80px;
  position: relative;
}

figure button {
  opacity: 0;
  position: absolute;
  right: -1rem;
  top: 1rem;
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
