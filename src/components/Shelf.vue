<template>
  <h1 class="text-h5 text-secondary text-left q-my-none">{{ $t("book.bookcase", { name: shelfName }) }}</h1>
  <section>
    <figure v-for="book in books" :key="book.id">
      <q-btn
        round
        color="negative"
        icon="close"
        size="sm"
        :title="$t('book.remove')"
        @click="$emit('emitID', book.id)"
      />
      <Tooltip :label="book.title" position="is-bottom">
        <img :src="book.thumbnail" :alt="`Livro ${book.title}`" />
      </Tooltip>
    </figure>
  </section>
</template>

<script>
import Tooltip from "@adamdehaven/vue-custom-tooltip";

export default {
  name: "Shelf",
  components: { Tooltip },
  props: {
    shelfName: { type: String, required: true },
    books: { type: Array, required: true },
  },
  emits: ["emitID"],
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
