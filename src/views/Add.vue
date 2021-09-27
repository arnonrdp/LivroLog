<template>
	<Header />
	<div v-show="show" class="addSuccess">
		<p>The book has been successfully added.</p>
	</div>
	<form action="#" @submit.prevent="submit">
		<Input v-model="seek" type="text" :label="$t('addlabel')">
			<Button :text="$t('search')" @click="search" />
		</Input>
	</form>
	<div id="results">
		<figure v-for="(book, index) in booksApi" :key="index">
			<Button
				text="+"
				@click="add(book.id, book.title, book.authors, book.thumbnail)"
			/>
			<a><img :src="book.thumbnail || noCover" alt="" /></a>
			<figcaption>{{ book.title }}</figcaption>
			<figcaption id="authors">
				<span v-for="(author, i) in book.authors" :key="i">
					<span>{{ author || unknown }}</span>
					<span v-if="i + 1 < book.authors.length">, </span>
				</span>
			</figcaption>
		</figure>
	</div>
</template>

<script>
import Header from "@/components/TheHeader.vue";
import Input from "@/components/BaseInput.vue";
import Button from "@/components/BaseButton.vue";

export default {
	name: "Add",
	components: { Header, Input, Button },
	data() {
		return {
			seek: "",
			results: "",
			books: {},
			noCover: require("../assets/no_cover.jpg"),
			unknown: ["Unknown"],
			show: false,
		};
	},
	computed: {
		booksApi() {
			let res = this.$store.state.booksApi;
			return res;
		},
	},
	methods: {
		showSuccess() {
			this.show = true;
			setTimeout(() => {
				this.show = false;
			}, 1000);
		},
		async search() {
			await this.$store.commit("search", this.seek);
		},
		async add(bookID, title, authors, thumbnail) {
			await this.$store.commit("add", {
				bookID,
				title,
				authors,
				thumbnail,
			});
			this.showSuccess();
		},
	},
};
</script>

<style scoped>
.addSuccess {
	position: fixed;
	top: 0%;
	margin-left: auto;
	margin-right: auto;
	width: 100%;
	background-color: #4bb6aa;
	z-index: 10;
	height: 60px;
}
form {
	width: 100%;
}

form input {
	overflow: visible;
	outline: 0;
	width: 70%;
	padding: 10px;
	border-radius: 18px;
	background-color: #dee3e6;
	background-clip: padding-box;
	border: 0.5px solid #d1d9e6;
	box-shadow: var(--low-shadow);
}

form button {
	margin: 0;
	position: absolute;
	right: 9%;
	top: -1px;
}

input:focus ~ button {
	right: 6%;
}

#results {
	display: flex;
	flex-flow: row wrap;
	justify-content: center;
	align-items: flex-start;
	align-content: center;
}

figure {
	padding-top: 5px;

	display: flex;
	flex-direction: column;
	flex-wrap: nowrap;
	align-content: center;
	justify-content: flex-start;
	align-items: center;
	position: relative;
}

figure button {
	margin: -2.5rem 2.5rem;
	opacity: 0;
	position: absolute;
	visibility: hidden;
}

figure:hover button,
figure button:hover {
	font-weight: bolder;
	opacity: 1;
	transition: 0.5s;
	visibility: visible;
	width: 100%;
}

#results img {
	width: 8rem;
}

figcaption {
	max-width: 8rem;
}

#authors {
	font-size: 12px;
	font-weight: bold;
}
</style>
