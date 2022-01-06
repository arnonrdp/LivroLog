import {
  createUserWithEmailAndPassword,
  getAdditionalUserInfo,
  GoogleAuthProvider,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signInWithPopup,
  signOut,
} from "firebase/auth";
import { doc, getDoc, setDoc } from "firebase/firestore";
import { auth, db } from "../../firebase";
import router from "../../router";

const state = {
  userProfile: {},
};

const getters = {
  getUserProfile(state) {
    return state.userProfile;
  },
  isAuthenticated(state) {
    return state.userProfile.email !== undefined;
  },
};

const mutations = {
  setUserProfile(state, val) {
    state.userProfile = val;
  },
  setUserBooks(state, val) {
    state.userProfile.books?.push(val) ?? (state.userProfile.books = val);
  },
  removeBook(state, id) {
    const index = state.userProfile.books.findIndex((book) => book.id === id);
    state.userProfile.books.splice(index, 1);
  },
  updateBookReadDate(state, payload) {
    payload.map((book) => {
      const index = state.userProfile.books.findIndex((userBook) => userBook.id === book.id);
      state.userProfile.books[index].readIn = book.readIn;
    });
  },
};

const actions = {
  async login({ commit, dispatch }, payload) {
    await signInWithEmailAndPassword(auth, payload.email, payload.password)
      .then((firebaseData) => {
        dispatch("fetchUserProfile", firebaseData.user);
        commit("setError", null);
      })
      .catch((error) => commit("setError", error));
  },
  async logout({ commit }) {
    //TODO: Testar logout sem async/await
    await signOut(auth);
    commit("setUserProfile", {});
    router.push("login");
  },
  async signup({ commit }, payload) {
    await createUserWithEmailAndPassword(auth, payload.email, payload.password)
      .then(async (userCredential) => {
        const userID = userCredential.user.uid;
        await setDoc(doc(db, "users", userID), {
          displayName: payload.name,
          email: payload.email,
          shelfName: payload.name,
        });
        commit("setError", null);
      })
      .catch((error) => commit("setError", error));
  },
  async googleSignIn({ commit, dispatch }) {
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
      .catch((error) => commit("setError", error));
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
    await sendPasswordResetEmail(auth, payload.email)
      .then(() => commit("setError", null))
      .catch((error) => commit("setError", error));
  },
};

export default {
  // TODO: Verificar necessidade de uso do namespaced
  // namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
