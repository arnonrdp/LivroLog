<template>
  <div>
    <input
      :type="type"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      placeholder=" "
      required
    />
    <label>{{ label }}</label>
    <span></span>
    <slot></slot>
  </div>
</template>

<script>
export default {
  name: "Input",
  props: ["type", "modelValue", "label"],
  emits: ["update:modelValue"],
};
</script>

<style scoped>
:focus {
  outline: none;
}

div {
  font: Arial, sans-serif;
  margin: 40px 3%;
  position: relative;
}

input {
  background-color: transparent;
  border: 0;
  border-bottom: 1px solid #ccc;
  color: #333;
  box-sizing: border-box;
  font: 1rem/1.5rem;
  letter-spacing: 1px;
  padding: 4px 0;
  width: 100%;
}

input ~ label {
  color: #777;
  font-size: 0.9rem;
  left: 0;
  letter-spacing: 0.5px;
  position: absolute;
  top: 6px;
  transition: 0.3s;
  /* width: 100%;
  z-index: -1; */
}

input:focus ~ label,
input:not(:placeholder-shown) ~ label {
  color: #4bb6aa;
  font-size: 0.75rem;
  top: -1rem;
  transition: 0.3s;
}

input ~ span {
  background-color: #4bb6aa;
  bottom: 0;
  height: 2px;
  left: 50%;
  position: absolute;
  transition: 0.4s;
  width: 0;
}

input:focus ~ span,
input:not(:placeholder-shown) ~ span {
  left: 0;
  transition: 0.4s;
  width: 100%;
}
</style>
