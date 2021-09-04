<template>
  <section>
    <figure>
      <a href="#"><img src="#" alt="Livro"></a>
      <figcaption>livro.title</figcaption>
    </figure>
  </section>
</template>

<script>
export default {
  name: 'Shelf',
}
</script>

<style scoped>
section {
  min-height: 285px;
	background-image: url('~@/assets/shelfleft.png'), url('~@/assets/shelfright.png'), url('~@/assets/shelfcenter.png');
	background-repeat: repeat-y, repeat-y, repeat;
	background-position: top left, top right, 240px 0;
}
</style>