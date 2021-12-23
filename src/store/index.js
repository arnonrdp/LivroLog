import {
  createUserWithEmailAndPassword,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signOut,
} from "firebase/auth";
import { doc, getDoc, setDoc } from "firebase/firestore";
import { createStore } from "vuex";
import { auth, db } from "../firebase";
import router from "../router";

const store = createStore({
  state: {
    userProfile: {},
    error: null,
    loading: false,
    information: null,
    count: 0,
  },
  getters: {
    getUserProfile(state) {
      return state.userProfile;
    },
    isAuthenticated(state) {
      return !!state.userProfile;
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
    setInformation(state, payload) {
      state.information = payload;
    },
    setError(state, payload) {
      state.error = payload;
    },
    setLoading(state, payload) {
      state.loading = payload;
    },
    increment(state) {
      state.count++;
    },
  },
  actions: {
    async login({ commit, dispatch }, payload) {
      commit("setLoading", true);
      await signInWithEmailAndPassword(auth, payload.email, payload.password)
        .then((firebaseData) => {
          dispatch("fetchUserProfile", firebaseData.user);
          commit("setError", null);
        })
        .catch((error) => commit("setError", { login: error }))
        .finally(() => commit("setLoading", false));
    },
    async logout({ commit }) {
      await signOut(auth);
      commit("setUserProfile", {});
      router.push({ name: "Login" });
    },
    async signup({ commit }, payload) {
      console.log("payload", payload);
      commit("setLoading", true);
      await createUserWithEmailAndPassword(auth, payload.email, payload.password)
        .then(async (userCredential) => {
          const userID = userCredential.user.uid;
          await setDoc(doc(db, "users", userID), {
            name: payload.name,
            email: payload.email,
            shelfName: payload.name,
          });
          commit("setInformation", { signUp: { code: "Success", message: `User created! Go to Login` } });
          commit("setError", null);
        })
        .catch((error) => {
          commit("setInformation", null);
          commit("setError", { signUp: error });
        })
        .finally(() => commit("setLoading", false));
    },
    async fetchUserProfile({ commit, dispatch }, user) {
      commit("setLoading", true);
      const userRef = doc(db, "users", user.uid);
      await getDoc(userRef)
        .then((firebaseData) => {
          const userInfo = firebaseData.data();
          commit("setUserProfile", userInfo?.enable ? userInfo : {});
          if (userInfo) {
            commit("setError", null);
            router.push("/");
          }
        })
        .catch((error) => commit("setError", error))
        .finally(() => commit("setLoading", false));
    },
    async resetPassword({ commit }, payload) {
      commit("setLoading", true);
      await sendPasswordResetEmail(auth, payload.email)
        .then(() => {
          commit("setInformation", {
            resetPassword: { code: "Success", message: "Success!, check your email for the password reset link" },
          });
          commit("setError", null);
        })
        .catch((error) => {
          commit("setInformation", null);
          commit("setError", { resetPassword: error });
        })
        .finally(() => commit("setLoading", false));
    },
    increment: ({ commit }) => commit("increment"),
  },
});

// store.dispatch("increment");

export default store;
