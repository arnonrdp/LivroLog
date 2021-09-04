<template>
  <router-view />
</template>

<style>
:root {
	--primary-bg: #e6e7ee;
	--primary-text: #44476a;
	--link-color: #333333;
  --high-shadow: 3px 3px 6px #b8b9be, -3px -3px 6px #ffffff;
  --low-shadow: inset 2px 2px 5px #b8b9be, inset -3px -3px 7px #ffffff;
}

body {
  margin: 5px;
	background-color: var(--primary-bg);
}

#app {
  color: var(--primary-text);
  font-family: 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif;
  text-align: center;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
</style>
