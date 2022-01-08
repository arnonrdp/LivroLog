import { deleteDoc, doc, setDoc, writeBatch } from "firebase/firestore";
import { db } from "../../firebase";

const batch = writeBatch(db);

const state = {
  books: [],
};

const getters = {
  getBooks(state) {
    return state.books;
  },
};

const mutations = {
  setBooks(state, val) {
    state.books = [...state.books].concat(val);
  },
  clearBooks(state) {
    state.books = [];
  },
  removeBook(state, id) {
    const index = state.books.findIndex((book) => book.id === id);
    state.books.splice(index, 1);
  },
  updateBookReadDate(state, payload) {
    payload.map((book) => {
      const index = state.books.findIndex((userBook) => userBook.id === book.id);
      state.books[index].readIn = book.readIn;
    });
  },
};

const actions = {
  async addBook({ commit, rootGetters }, payload) {
    if (rootGetters.getBooks.some((userBook) => userBook.id === payload.id)) {
      throw new Error("Book already exists");
    }
    await setDoc(doc(db, "users", rootGetters.getUserID, "addedBooks", payload.id), { ...payload })
      .then(() => commit("setBooks", payload))
      .catch((error) => console.error(error));
  },
  async updateReadDates({ commit, rootGetters }, payload) {
    payload.map((book) => {
      batch.update(doc(db, "users", rootGetters.getUserID, "addedBooks", book.id), { readIn: book.readIn });
    });
    await batch.commit()
      .then(() => commit("updateBookReadDate", payload))
      .catch((error) => console.error(error));
  },
  async removeBook({ commit, rootGetters }, payload) {
    await deleteDoc(doc(db, "users", rootGetters.getUserID, "addedBooks", payload))
      .then(() => commit("removeBook", payload))
      .catch((error) => console.error(error));
  },
};

export default {
  state,
  getters,
  mutations,
  actions,
};
