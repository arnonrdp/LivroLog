import { deleteDoc, doc, setDoc, updateDoc, writeBatch } from "firebase/firestore";
import { db } from "../../firebase";
import store from "../index";

const batch = writeBatch(db);

const getters = {
  getUserBooks() {
    return store.getters.getUserProfile.books;
  },
};

const actions = {
  async updateShelfName({ commit, rootGetters }, payload) {
    const userID = rootGetters.getUserProfile.uid;

    await updateDoc(doc(db, "users", userID), { shelfName: payload })
      .then(() => commit("setUserShelfName", payload))
      .catch((error) => console.error(error));
  },
  async addBook({ commit, rootGetters }, payload) {
    if (getters.getUserBooks().some((userBook) => userBook.id === payload.id)) {
      throw new Error("Book already exists");
    }
    const userID = rootGetters.getUserProfile.uid;

    await setDoc(doc(db, "users", userID, "addedBooks", payload.id), { ...payload })
      .then(() => commit("setUserBooks", payload))
      .catch((error) => console.error(error));
  },
  async updateReadDates({ commit, rootGetters }, payload) {
    const userID = rootGetters.getUserProfile.uid;

    payload.map((book) => {
      batch.update(doc(db, "users", userID, "addedBooks", book.id), { readIn: book.readIn });
    });
    await batch.commit()
      .then(() => commit("updateBookReadDate", payload))
      .catch((error) => console.error(error));
  },
  async removeBook({ commit, rootGetters }, payload) {
    const userID = rootGetters.getUserProfile.uid;

    await deleteDoc(doc(db, "users", userID, "addedBooks", payload))
      .then(() => commit("removeBook", payload))
      .catch((error) => console.error(error));
  },
};

export default {
  getters,
  actions,
};
