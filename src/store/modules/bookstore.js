import { collection, deleteDoc, doc, getDocs, runTransaction, setDoc } from "firebase/firestore";
import { db } from "../../firebase";

const state = {
  books: [],
};

const getters = {
  getMyBooks(state) {
    return state.books;
  },
};

const mutations = {
  setMyBooks(state, val) {
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
    for (const book of payload) {
      const index = state.books.findIndex((userBook) => userBook.id === book.id);
      state.books[index].readIn = book.readIn;
    }
  },
};

const throwError = (error) => {
  throw error.code;
};

const actions = {
  async addBook({ commit, dispatch, rootGetters }, payload) {
    if (rootGetters.getMyBooks.some((userBook) => userBook.id === payload.id)) {
      throwError({ code: "book_already_exists" });
    }

    await setDoc(doc(db, "users", rootGetters.getMyID, "books", payload.id), { ...payload })
      .then(() => {
        commit("setMyBooks", payload);
        dispatch("modifiedAt", rootGetters.getMyID);
      })
      .catch(throwError);
  },

  async updateReadDates({ commit, dispatch, rootGetters }, payload) {
    await runTransaction(db, async (transaction) => {
      for (const book of payload) {
        transaction.update(doc(db, "users", rootGetters.getMyID, "books", book.id), { readIn: book.readIn });
      }
    })
      .then(() => {
        commit("updateBookReadDate", payload);
        dispatch("modifiedAt", rootGetters.getMyID);
      })
      .catch(throwError);
  },

  async removeBook({ commit, dispatch, rootGetters }, payload) {
    await deleteDoc(doc(db, "users", rootGetters.getMyID, "books", payload))
      .then(() => {
        commit("removeBook", payload);
        dispatch("modifiedAt", rootGetters.getMyID);
      })
      .catch(throwError);
  },

  async queryDBMyBooks({ commit, rootGetters }) {
    await getDocs(collection(db, "users", rootGetters.getMyID, "books"))
      .then((querySnapshot) => {
        const books = querySnapshot.docs.map((doc) => doc.data());
        commit("clearBooks");
        commit("setMyBooks", books);
        return books;
      })
      .catch(throwError);
  },
};

export default {
  state,
  getters,
  mutations,
  actions,
};
