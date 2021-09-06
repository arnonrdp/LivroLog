<template>
  <button @click="$emit('click')">{{ text }}</button>
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
  margin: 5px 0;
  padding: 10px;
  border-radius: 18px;
  background-color: #dee3e6;
  border: 0.5px solid transparent;
  box-shadow: var(--high-shadow);
}

button:hover {
  box-shadow: var(--low-shadow);
}
button:hover img {
  transform: scale(0.95);
}
</style>
