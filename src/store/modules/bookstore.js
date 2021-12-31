import { deleteDoc, doc, setDoc, updateDoc } from "firebase/firestore";
import { db } from "../../firebase";
import store from "../index";

const getters = {
  getUserBooks() {
    return store.getters.getUserProfile.books;
  },
};

const actions = {
  async updateShelfName({ commit, rootState, rootGetters }, payload) {
    commit("setLoading", true);
    const userRef = doc(db, "users", rootGetters.getUserProfile.uid);
    await updateDoc(userRef, { shelfName: payload })
      .then(() => {
        rootState.authentication.userProfile.shelfName = payload;
        commit("setUserProfile", rootState.authentication.userProfile);
        commit("setError", null);
      })
      .catch((error) => commit("setError", error))
      .finally(() => commit("setLoading", false));
  },
  async addBook({ commit, rootGetters }, payload) {
    if (getters.getUserBooks().some((userBook) => userBook.id === payload.id)) {
      console.log("Book already exists");
      return;
    }

    commit("setUserBooks", payload);
    commit("setLoading", true);

    const userID = rootGetters.getUserProfile.uid;

    await setDoc(doc(db, "users", userID, "addedBooks", payload.id), {
      ...payload,
      addedIn: new Date(),
      readIn: null,
    });
    commit("setLoading", false);
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
