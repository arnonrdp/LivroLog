<template>
  <router-view />
</template>

<style>
:root {
  --primary-bg: #e6e7ee;
  --primary-text: #44476a;
  --link-color: #2c3e50;
  --high-shadow: 3px 3px 6px #b8b9be, -3px -3px 6px #ffffff;
  --low-shadow: inset 2px 2px 5px #b8b9be, inset -3px -3px 7px #ffffff;
}

html {
  font-size: 16px;
}
@media screen and (max-width: 720px) {
  html {
    font-size: 15px;
  }
}
@media screen and (max-width: 640px) {
  html {
    font-size: 14px;
  }
}
@media screen and (max-width: 560px) {
  html {
    font-size: 13px;
  }
}
@media screen and (max-width: 480px) {
  html {
    font-size: 12px;
  }
}
@media screen and (max-width: 400px) {
  html {
    font-size: 11px;
  }
}
@media screen and (max-width: 320px) {
  html {
    font-size: 10px;
  }
}

body {
  margin: 5px;
  background-color: var(--primary-bg);
}

#app {
  color: var(--primary-text);
  font-family: "Helvetica Neue", "Helvetica", "Arial", sans-serif;
  text-align: center;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
</style>
