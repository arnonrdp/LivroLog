import { deleteDoc, doc, setDoc, updateDoc } from "firebase/firestore";
import { db } from "../../firebase";

const getters = {
  getUserBooks(state) {
    return state.authentication.userProfile.books;
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
    // TODO: Não permitir adicionar o mesmo livro duas vezes
    commit("setUserBooks", payload);
    commit("setLoading", true);

    const userID = rootGetters.getUserProfile.uid;

    await setDoc(doc(db, "users", userID, "addedBooks", payload.id), {
      ...payload,
      addedIn: new Date(),
      readIn: null,
    });
  },
  async removeBook({ commit, rootGetters }, payload) {
    console.log(payload);
    commit("setLoading", true);
    const userID = rootGetters.getUserProfile.uid;

    await deleteDoc(doc(db, "users", userID, "addedBooks", payload));
    //TODO: Remover também do LocalStorage
  },
};

export default {
  getters,
  actions,
};
