import { arrayUnion, doc, runTransaction, setDoc, updateDoc } from "firebase/firestore";
import SecureLS from "secure-ls";
import { createStore } from "vuex";
import createPersistedState from "vuex-persistedstate";
import { db } from "../firebase";
import authentication from "./modules/authentication";

const ls = new SecureLS({ isCompression: false });

const store = createStore({
  state: {
    error: null,
    information: null,
    loading: false,
  },
  plugins: [
    createPersistedState({
      //   storage: {
      //     getItem: (key) => ls.get(key),
      //     setItem: (key, value) => ls.set(key, value),
      //     removeItem: (key) => ls.remove(key),
      //   },
    }),
  ],
  getters: {
    // getUserProfile(state) {
    //   return state.userProfile;
    // },
    getUserBooks(state) {
      return state.authentication.userProfile.books;
    },
    // isAuthenticated(state) {
    //   return state.userProfile.email !== undefined;
    // },
    getError(state) {
      return state.error;
    },
    getInformation(state) {
      return state.information;
    },
    getLoading(state) {
      return state.loading;
    },
  },
  mutations: {
    setUserBooks(state, val) {
      state.authentication.userProfile.books?.push(val) ?? (state.authentication.userProfile.books = [val]);
    },
    setError(state, payload) {
      state.error = payload;
    },
    setInformation(state, payload) {
      state.information = payload;
    },
    setLoading(state, payload) {
      state.loading = payload;
    },
  },
  actions: {
    // BOOKSHELF
    async updateShelfName({ commit }, payload) {
      commit("setLoading", true);
      const userRef = doc(db, "users", this.state.authentication.userProfile.uid);
      await updateDoc(userRef, { shelfName: payload })
        .then(() => {
          this.state.authentication.userProfile.shelfName = payload;
          commit("setUserProfile", this.state.authentication.userProfile);
          commit("setError", null);
        })
        .catch((error) => commit("setError", error))
        .finally(() => commit("setLoading", false));
    },
    async addBook({ commit }, payload) {
      // TODO: NÃO PERMITIR ADICIONAR MESMO LIVRO DUAS VEZES
      commit("setUserBooks", payload);
      commit("setLoading", true);
      const userID = this.state.authentication.userProfile.uid;
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
    // TODO: MÉTODO PARA REMOVER LIVRO DO USUÁRIO
    async removeBook({ commit }, payload) {
      console.log(payload);
      commit("setLoading", true);
      const userID = this.state.authentication.userProfile.uid;
      const bookRef = doc(db, "books", payload);
      try {
        await runTransaction(db, async (transaction) => {
          const sfDoc = await transaction.get(bookRef);
          // TODO: VERIFICAR NECESSIDADE DESTE IF
          if (sfDoc.exists()) {
            await updateDoc(bookRef, { readers: arrayRemove(userID) });
          }
        });
      } catch (error) {
        commit("setError", error);
      } finally {
        commit("setLoading", false);
        // TODO: TESTAR REMOÇÃO DE LIVROS
        await updateDoc(doc(db, "users", userID, "addedBooks", payload), {
          bookRef: bookRef,
          addedIn: new Date(),
          readIn: "",
        });
      }
    },
  },
  modules: { authentication },
});

export default store;
