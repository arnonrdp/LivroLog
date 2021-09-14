<template>
  <input
    :type="type"
    :value="modelValue"
    @input="$emit('update:modelValue', $event.target.value)"
    :placeholder="placeholder"
  />
</template>

<script>
export default {
  name: "Input",
  props: ["type", "modelValue", "placeholder"],
  emits: ["update:modelValue"],
};
</script>

<style scoped>
input {
  background-clip: padding-box;
  background-color: #dee3e6;
  border: 0.5px solid #d1d9e6;
  border-radius: 18px;
  box-shadow: var(--low-shadow);
  margin: 5px 0;
  padding: 10px;
  outline: 0;
  overflow: visible;
  width: 80%;
}
</style>
