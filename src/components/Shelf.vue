<template>
  <section>
    <figure>
      <a href="#"><img src="#" alt="Livro"/></a>
      <figcaption>TÍTULO</figcaption>
    </figure>
    <figure>
      <a href="#"><img src="#" alt="Livro"/></a>
      <figcaption>TÍTULO</figcaption>
    </figure>
    <figure>
      <a href="#"><img src="#" alt="Livro"/></a>
      <figcaption>TÍTULO</figcaption>
    </figure>
  </section>
</template>

<script>
export default {
  name: "Shelf",
};
</script>

<style scoped>
section {
  min-height: 285px;
  background-image: url("~@/assets/shelfleft.png"),
    url("~@/assets/shelfright.png"), url("~@/assets/shelfcenter.png");
  background-repeat: repeat-y, repeat-y, repeat;
  background-position: top left, top right, 240px 0;
  padding: 0 30px 15px 30px;
  border-radius: 6px;
  display: flex;
  flex-flow: row wrap;
  justify-content: space-around;
}

section figure {
  position: relative;
  display: flex;
  align-items: flex-end;
  margin: 0 30px;
  height: 143.5px;
  max-width: 80px;
}
</style>
