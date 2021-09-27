<template>
	<main>
		<h1>{{ $t("shelf", { name: shelfName }) }}</h1>
		<section>
			<figure v-for="book in books" :key="book.id">
				<Tooltip :label="book.title" position="is-bottom">
					<img :src="book.thumbnail" :alt="`Livro ${book.title}`" />
				</Tooltip>
			</figure>
		</section>
	</main>
</template>

<script>
import Tooltip from "@adamdehaven/vue-custom-tooltip";

export default {
	name: "Shelf",
	components: { Tooltip },
	async mounted() {
		if (this.$store.state.index == 0) {
			await this.$store.getters.getBooks;
			await this.$store.commit("getShelfName");
		}
	},
	computed: {
		books() {
			return this.$store.state.books;
		},
		shelfName() {
			return this.$store.state.shelfName;
		},
	},
};
</script>

<style scoped>
main {
	margin: 0 10px;
}

h1 {
	border: 0.5px solid transparent;
	border-radius: 18px;
	color: #491f00;
	font-size: 1.5rem;
	letter-spacing: 1px;
	margin: 0;
	width: fit-content;
}

section {
	background-image: url("~@/assets/shelfleft.png"),
		url("~@/assets/shelfright.png"), url("~@/assets/shelfcenter.png");
	background-repeat: repeat-y, repeat-y, repeat;
	background-position: top left, top right, 240px 0;
	border-radius: 6px;
	display: flex;
	flex-flow: row wrap;
	justify-content: space-around;
	min-height: 285px;
	padding: 0 30px 15px 30px;
}

section figure {
	align-items: flex-end;
	display: flex;
	height: 143.5px;
	margin: 0 30px;
	max-width: 80px;
	position: relative;
}

figure button {
	opacity: 0;
	position: absolute;
	visibility: hidden;
	width: 103%;
	height: 33%;
	color: #cb0909;
	font-size: x-large;
	z-index: 1;
	background-color: #def9ed;
	cursor: pointer;
}

figure:hover button,
figure button:hover {
	opacity: 1;
	visibility: visible;
}

img {
	height: 115px;
}
</style>
