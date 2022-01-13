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
    payload.map((book) => {
      const index = state.books.findIndex((userBook) => userBook.id === book.id);
      state.books[index].readIn = book.readIn;
    });
  },
};

const actions = {
  async addBook({ commit, dispatch, rootGetters }, payload) {
    if (rootGetters.getMyBooks.some((userBook) => userBook.id === payload.id)) {
      throw new Error("Book already exists");
    }
    await setDoc(doc(db, "users", rootGetters.getMyID, "books", payload.id), { ...payload })
      .then(() => {
        commit("setMyBooks", payload);
        dispatch("modifiedAt", rootGetters.getMyID);
      })
      .catch((error) => console.error(error));
  },

  async updateReadDates({ dispatch, rootGetters }, payload) {
    await runTransaction(db, async (transaction) => {
      payload.map((book) => {
        transaction.update(doc(db, "users", rootGetters.getMyID, "books", book.id), { readIn: book.readIn });
      });
    })
      .then(() => dispatch("modifiedAt", rootGetters.getMyID))
      .catch((error) => console.error(error));
  },

  async removeBook({ commit, dispatch, rootGetters }, payload) {
    await deleteDoc(doc(db, "users", rootGetters.getMyID, "books", payload))
      .then(() => {
        commit("removeBook", payload);
        dispatch("modifiedAt", rootGetters.getMyID);
      })
      .catch((error) => console.error(error));
  },
  
  async queryDBMyBooks({ commit, rootGetters }) {
    await getDocs(collection(db, "users", rootGetters.getMyID, "books"))
      .then((querySnapshot) => {
        const books = querySnapshot.docs.map((doc) => doc.data());
        commit("clearBooks");
        commit("setMyBooks", books);
        return books;
      })
      .catch((error) => console.error(error));
  },
};

export default {
  state,
  getters,
  mutations,
  actions,
};
