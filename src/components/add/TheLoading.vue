<template>
  <div class="loader">
    <svg
      class="book-wrapper"
      version="1.1"
      id="Layer_1"
      xmlns="http://www.w3.org/2000/svg"
      xmlns:xlink="http://www.w3.org/1999/xlink"
      x="0px"
      y="0px"
      viewBox="0 0 260 260"
      style="enable-background: new 0 0 260 260"
      xml:space="preserve"
    >
      <g>
        <path
          class="book-inner"
          d="M134.7,191.2c-0.4-0.1-0.8-0.1-1.2-0.1s-0.8,0-1.2,0.1L49,202.3V70.7l83.3,11.1c0.4,0.1,0.8,0.1,1.2,0.1 
        s0.8,0,1.2-0.1L218,70.7v131.6L134.7,191.2z"
        />
        <path
          class="book-border"
          d="M54,76.4l77.7,10.3c0.6,0.1,1.2,0.1,1.8,0.1s1.2,0,1.8-0.1L213,76.4v120.2l-77.7-10.3
        c-0.6-0.1-1.2-0.1-1.8-0.1s-1.2,0-1.8,0.1L54,196.6V76.4 M219,65.6c-0.2,0-0.4,0-0.5,0L134,76.8c-0.2,0-0.4,0-0.5,0s-0.4,0-0.5,0
        L48.5,65.6c-0.2,0-0.4,0-0.5,0c-2.2,0-4,1.8-4,4v133.9c0,2.2,1.8,4,4,4c0.2,0,0.4,0,0.5,0l84.4-11.2c0.2,0,0.4,0,0.5,0s0.4,0,0.5,0
        l84.4,11.2c0.2,0,0.4,0,0.5,0c2.2,0,4-1.8,4-4V69.6C223,67.3,221.2,65.6,219,65.6L219,65.6z"
        />
      </g>
    </svg>
    <!-- page svg -->
    <svg
      class="page-wrapper"
      version="1.1"
      id="Layer_1"
      xmlns="http://www.w3.org/2000/svg"
      xmlns:xlink="http://www.w3.org/1999/xlink"
      x="0px"
      y="0px"
      viewBox="0 0 260 260"
      style="enable-background: new 0 0 260 260"
      xml:space="preserve"
    >
      <g>
        <polygon class="page-inner" points="138,191.6 138,81.4 218,70.7 218,202.3 	" />
        <g>
          <path
            class="page-border"
            d="M213,76.4v120.2l-70-9.3V85.7L213,76.4 M219,65.6c-0.2,0-0.4,0-0.5,0L134,76.8c-0.2,0-0.4,0-0.5,0
          s-0.4,0-0.5,0v119.3c0.2,0,0.4,0,0.5,0s0.4,0,0.5,0l84.4,11.2c0.2,0,0.4,0,0.5,0c2.2,0,4-1.8,4-4V69.6
          C223,67.3,221.2,65.6,219,65.6L219,65.6z"
          />
        </g>
      </g>
      <g>
        <polygon class="page-inner" points="213,197 132,186 132,87 213,76 	" />
      </g>
    </svg>
  </div>
</template>

<style scoped>
.loader {
  width: 100%;
  margin: 39px auto;
}

.book-wrapper {
  left: 45%;
  position: relative;
  margin: 0 auto;
  width: 10%;
}
.book-wrapper .book-inner {
  fill: #fff;
}
.book-wrapper .book-border {
  fill: #d8d8d8;
}

.page-wrapper {
  position: absolute;
  width: 9.89%;
  animation: flip 1.5s infinite linear;
  animation-fill-mode: forwards;
}
.page-wrapper .page-inner {
  fill: #f4f4f4;
  animation: fill 1.5s infinite linear;
  animation-fill-mode: forwards;
}
.page-wrapper .page-border {
  fill: #d8d8d8;
}

@keyframes flip {
  0% {
    left: 45%;
    transform: perspective(600px) rotateY(0deg);
  }
  30% {
    transform: perspective(200px) rotateY(-90deg);
  }
  54.999% {
    opacity: 1;
  }
  55% {
    opacity: 0;
  }
  60% {
    left: 45.28%;
    transform: perspective(200px) rotateY(-180deg);
  }
  100% {
    left: 45.28%;
    transform: perspective(200px) rotateY(-180deg);
  }
}
@keyframes fill {
  20% {
    fill: #f4f4f4;
  }
  29.9% {
    fill: #d8d8d8;
  }
  60% {
    fill: #fff;
  }
  100% {
    fill: #fff;
  }
}
</style>
