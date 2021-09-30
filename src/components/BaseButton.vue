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
  background-color: #4bb6aa;
  background-position: center;
  border: none;
  border-radius: 6px;
  box-shadow: 0px 3px 1px -2px rgb(0 0 0 / 20%),
    0px 2px 2px 0px rgb(0 0 0 / 14%), 0px 1px 5px 0px rgb(0 0 0 / 12%);
  color: white;
  cursor: pointer;
  font-size: 0.95rem;
  padding: 0.75rem 1.25rem;
  outline: none;
  transition: all 0.5s;
}
button:not(:disabled) {
  color: #263238;
  background-color: #d4e1e6;
}
button:hover,
button:focus, button.active {
  background: #4bb6aa radial-gradient(circle, transparent 1%, #4bb6aa 1%)
    center/15000%;
  box-shadow: 0px 2px 4px -1px rgb(0 0 0 / 20%),
    0px 4px 5px 0px rgb(0 0 0 / 14%), 0px 1px 10px 0px rgb(0 0 0 / 12%);
  color: white;
  transition: all 0.5s;
}
button:active {
  background-color: #6ecec3;
  background-size: 100%;
  box-shadow: 0px 5px 5px -3px rgb(0 0 0 / 20%),
    0px 8px 10px 1px rgb(0 0 0 / 14%), 0px 3px 14px 2px rgb(0 0 0 / 12%);
  transition: box-shadow 280ms cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
