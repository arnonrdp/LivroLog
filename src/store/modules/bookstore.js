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
  async updateShelfName({ commit, rootState, rootGetters }, payload) {
    const userID = rootGetters.getUserProfile.uid;
    await updateDoc(doc(db, "users", userID), { shelfName: payload })
      .then(() => {
        //TODO: Verificar se há redundância aqui
        rootState.authentication.userProfile.shelfName = payload;
        commit("setUserProfile", rootState.authentication.userProfile);
        commit("setError", null);
      })
      .catch((error) => commit("setError", error))
  },
  async addBook({ commit, rootGetters }, payload) {
    if (getters.getUserBooks().some((userBook) => userBook.id === payload.id)) {
      return console.log("Book already exists");
    }
    commit("setUserBooks", payload);
    
    const userID = rootGetters.getUserProfile.uid;
    await setDoc(doc(db, "users", userID, "addedBooks", payload.id), { ...payload });
  },
  async updateReadDates({ commit, rootGetters }, payload) {
    commit("updateBookReadDate", payload);
    
    const userID = rootGetters.getUserProfile.uid;
    payload.map((book) => {
      batch.update(doc(db, "users", userID, "addedBooks", book.id), { readIn: book.readIn });
    });
    await batch.commit();
  },
  async removeBook({ commit, rootGetters }, payload) {
    commit("removeBook", payload);

    const userID = rootGetters.getUserProfile.uid;
    await deleteDoc(doc(db, "users", userID, "addedBooks", payload));
  },
};

export default {
  getters,
  actions,
};
