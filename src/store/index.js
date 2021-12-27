import {
  createUserWithEmailAndPassword,
  getAdditionalUserInfo,
  GoogleAuthProvider,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signInWithPopup,
  signOut,
} from "firebase/auth";
import { arrayUnion, doc, getDoc, runTransaction, setDoc, updateDoc } from "firebase/firestore";
import SecureLS from "secure-ls";
import { createStore } from "vuex";
import createPersistedState from "vuex-persistedstate";
import { auth, db } from "../firebase";
import router from "../router";

const ls = new SecureLS({ isCompression: false });

const store = createStore({
  state: {
    userProfile: {},
    userBooks: [],
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
    getUserProfile(state) {
      return state.userProfile;
    },
    getUserBooks(state) {
      return state.userBooks;
    },
    isAuthenticated(state) {
      return state.userProfile.email !== undefined;
    },
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
    setUserProfile(state, val) {
      state.userProfile = val;
    },
    setUserBooks(state, val) {
      state.userBooks.push(val);
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
    // AUTHENTICATION
    async login({ commit, dispatch }, payload) {
      commit("setLoading", true);
      await signInWithEmailAndPassword(auth, payload.email, payload.password)
        .then((firebaseData) => {
          dispatch("fetchUserProfile", firebaseData.user);
          commit("setError", null);
        })
        .catch((error) => commit("setError", error))
        .finally(() => commit("setLoading", false));
    },
    async logout({ commit }) {
      await signOut(auth);
      commit("setUserProfile", {});
      router.push({ name: "Login" });
    },
    async signup({ commit }, payload) {
      commit("setLoading", true);
      await createUserWithEmailAndPassword(auth, payload.email, payload.password)
        .then(async (userCredential) => {
          const userID = userCredential.user.uid;
          await setDoc(doc(db, "users", userID), {
            displayName: payload.name,
            email: payload.email,
            shelfName: payload.name,
          });
          commit("setInformation", { code: "sign-up-success" });
          commit("setError", null);
        })
        .catch((error) => {
          commit("setInformation", null);
          commit("setError", error);
        })
        .finally(() => commit("setLoading", false));
    },
    async googleSignIn({ commit, dispatch }) {
      commit("setLoading", true);
      const provider = new GoogleAuthProvider();
      await signInWithPopup(auth, provider)
        .then(async (result) => {
          const { isNewUser } = getAdditionalUserInfo(result);
          if (isNewUser) {
            await setDoc(doc(db, "users", result.user.uid), {
              name: result.user.displayName,
              email: result.user.email,
              shelfName: result.user.displayName,
            });
          }
          dispatch("fetchUserProfile", result.user);
          commit("setError", null);
        })
        .catch((error) => commit("setError", error))
        .finally(() => commit("setLoading", false));
    },
    async fetchUserProfile({ commit }, user) {
      const userRef = doc(db, "users", user.uid);
      await getDoc(userRef)
        .then((firebaseData) => {
          const userInfo = firebaseData.data();
          userInfo.uid = firebaseData.id;
          commit("setUserProfile", (userInfo ??= {}));
          if (userInfo) {
            commit("setError", null);
            router.push("/");
          }
        })
        .catch((error) => commit("setError", error));
    },
    async resetPassword({ commit }, payload) {
      commit("setLoading", true);
      await sendPasswordResetEmail(auth, payload.email)
        .then(() => {
          commit("setInformation", {
            resetPassword: { code: "Success", message: "Success! Check your email for the password reset link" },
          });
          commit("setError", null);
        })
        .catch((error) => {
          commit("setInformation", null);
          commit("setError", error);
        })
        .finally(() => commit("setLoading", false));
    },
    // BOOKSHELF
    async updateShelfName({ commit }, payload) {
      commit("setLoading", true);
      const userRef = doc(db, "users", this.state.userProfile.uid);
      await updateDoc(userRef, { shelfName: payload })
        .then(() => {
          this.state.userProfile.shelfName = payload;
          commit("setUserProfile", this.state.userProfile);
          commit("setError", null);
        })
        .catch((error) => commit("setError", error))
        .finally(() => commit("setLoading", false));
    },
    async addBook({ commit }, payload) {
      commit("setUserBooks", payload);
      commit("setLoading", true);
      const userID = this.state.userProfile.uid;
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
      const userID = this.state.userProfile.uid;
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
});

export default store;
