import { arrayUnion, doc, runTransaction, setDoc, updateDoc } from "firebase/firestore";
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
    const bookRef = doc(db, "books", payload.id);
    try {
      await runTransaction(db, async (transaction) => {
        const sfDoc = await transaction.get(bookRef);
        if (sfDoc.exists()) {
          await updateDoc(bookRef, { readers: arrayUnion(userID) });
        } else {
          setDoc(bookRef, { readers: arrayUnion(userID) });
        }
      });
    } catch (error) {
      commit("setError", error);
    } finally {
      commit("setLoading", false);
      await setDoc(doc(db, "users", userID, "addedBooks", payload.id), {
        bookRef: bookRef,
        addedIn: new Date(),
        readIn: "",
      });
    }
  },
  // TODO: Método para remover livro da coleção do usuário
  async removeBook({ commit, rootGetters }, payload) {
    console.log(payload);
    commit("setLoading", true);
    const userID = rootGetters.getUserProfile.uid;
    const bookRef = doc(db, "books", payload);
    try {
      await runTransaction(db, async (transaction) => {
        const sfDoc = await transaction.get(bookRef);
        // TODO: Verificar necessidade deste if
        if (sfDoc.exists()) {
          await updateDoc(bookRef, { readers: arrayRemove(userID) });
        }
      });
    } catch (error) {
      commit("setError", error);
    } finally {
      commit("setLoading", false);
      // TODO: Testar remoção de livro do usuário
      await updateDoc(doc(db, "users", userID, "addedBooks", payload), {
        bookRef: bookRef,
        addedIn: new Date(),
        readIn: "",
      });
    }
  },
};

export default {
  getters,
  actions,
};
