import {
  createUserWithEmailAndPassword,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signOut,
} from "firebase/auth";
import { doc, setDoc } from "firebase/firestore";
import { createStore } from "vuex";
import { auth, db, usersCollection } from "../firebase";

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
    //TODO: CONFIGURE THIS
    async login({ commit, dispatch }, payload) {
      commit("setLoading", true);
      await signInWithEmailAndPassword(auth, payload.email, payload.password)
        .then((firebaseData) => {
          dispatch("fetchUserProfile", firebaseData.user);
          commit("setLoading", false);
          commit("setError", null);
        })
        .catch((error) => {
          commit("setLoading", false);
          commit("setError", { login: error });
        });
    },
    async logout({ commit }) {
      await signOut(auth);
      commit("setUserProfile", {});
      router.currentRoute.path !== "/auth" && router.push("/auth");
    },
    async signup({ commit }, payload) {
      commit("setLoading", true);
      await createUserWithEmailAndPassword(auth, payload.email, payload.password)
        .then(async (firebaseData) => {
          await setDoc(doc(db, "users", firebaseData.user.uid), {
            name: payload.name,
            email: payload.email,
            enable: true,
          }).then(() => {
            commit("setLoading", false);
            commit("setInformation", {
              signUp: { code: "Success", message: `User created!, use your new credentials` },
            });
            commit("setError", null);
          });
        })
        .catch((error) => {
          commit("setLoading", false);
          commit("setInformation", null);
          commit("setError", { signUp: error });
        });
    },
    //TODO: CONFIGURE THIS
    async fetchUserProfile({ commit, dispatch }, user) {
      commit("setLoading", true);
      await usersCollection
        .doc(user.uid)
        .get()
        .then((firebaseData) => {
          const userInfo = firebaseData.data();
          commit("setUserProfile", userInfo?.enable ? userInfo : {});
          if (userInfo) {
            !userInfo.enable && dispatch("logout");
            if (router.currentRoute.path === "/auth") {
              userInfo?.enable && router.push("/");
            }
            commit("setLoading", false);
            commit("setError", null);
          }
        })
        .catch((error) => {
          commit("setError", error);
          commit("setLoading", false);
        });
    },
    async resetPassword({ commit }, payload) {
      commit("setLoading", true);
      await sendPasswordResetEmail(auth, payload.email)
        .then((_) => {
          commit("setLoading", false);
          commit("setInformation", {
            resetPassword: {
              code: "Success",
              message: "Success!, check your email for the password reset link",
            },
          });
          commit("setError", null);
        })
        .catch((error) => {
          commit("setLoading", false);
          commit("setInformation", null);
          commit("setError", { resetPassword: error });
        });
    },
    increment: ({ commit }) => commit("increment"),
  },
});

store.dispatch("increment");

export default store;
