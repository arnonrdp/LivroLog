<template>
  <button @click="$emit('click')">{{ text }}<slot /></button>
</template>

<script>
export default {
  name: "Button",
  props: ["text"],
  emits: ["click"],
};
</script>

<style scoped>
button {
  background: #d4e1e6;
  border: 0;
  border-radius: 2px;
  color: #4bb6aa;
  cursor: pointer;
  display: block;
  font-size: 1rem;
  font-weight: 300;
  margin: 1.25rem auto;
  padding: 0.625rem 1.25rem;
  transition: all 0.4s;
  -webkit-transition: all 0.4s;
}

button:hover {
  background: #4bb6aa;
  color: #fff;
  transition: all 0.4s;
  -webkit-transition: all 0.4s;
}

button:active,
button.active {
  background: #4bb6aa;
  color: #263238;
}
</style>
