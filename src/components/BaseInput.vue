<template>
  <div>
    <input
      :type="type"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      required
    />
    <label>{{ label }}</label>
    <span class="bar"></span>
    <span class="highlight"></span>
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
div {
  margin: 30px 0;
  /* margin-left: 40px; */
  position: relative;
}

input {
  background: transparent;
  border: none;
  border-bottom: 1px solid #757575;
  display: block;
  font-size: 1rem;
  margin: 0 auto;
  padding: 10px 10px 10px 5px;
  width: 80%;
}

input:focus {
  outline: none;
}

label {
  color: #777;
  font-size: 0.9rem;
  font-weight: normal;
  left: 10%;
  position: absolute;
  pointer-events: none;
  top: 10px;
  transition: 0.2s ease all;
  -moz-transition: 0.2s ease all;
  -webkit-transition: 0.2s ease all;
}

input:focus ~ label,
input:valid ~ label {
  color: #4bb6aa;
  font-size: 0.9rem;
  top: -15px;
}

.bar {
  display: block;
  left: 6%;
  position: relative;
  width: 88%;
}

.bar:before,
.bar:after {
  background: #4bb6aa;
  bottom: 0;
  content: "";
  height: 2px;
  position: absolute;
  transition: 0.2s ease all;
  -moz-transition: 0.2s ease all;
  -webkit-transition: 0.2s ease all;
  width: 0;
}

.bar:before {
  left: 50%;
}

.bar:after {
  right: 50%;
}

input:focus ~ .bar:before,
input:focus ~ .bar:after {
  width: 50%;
}

input:focus ~ .highlight {
  animation: inputHighlighter 0.3s ease;
  -webkit-animation: inputHighlighter 0.3s ease;
  -moz-animation: inputHighlighter 0.3s ease;
}

/* ANIMATIONS */
@-webkit-keyframes inputHighlighter {
  from {
    background: #5264ae;
  }
  to {
    background: transparent;
    width: 0;
  }
}
@-moz-keyframes inputHighlighter {
  from {
    background: #5264ae;
  }
  to {
    background: transparent;
    width: 0;
  }
}
@keyframes inputHighlighter {
  from {
    background: #5264ae;
  }
  to {
    background: transparent;
    width: 0;
  }
}
</style>
