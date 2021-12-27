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
};

const actions = {
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
    router.push("login");
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
        console.log("userInfo", userInfo);
        userInfo.uid = firebaseData.id;
        commit("setUserProfile", (userInfo ??= {}));
        if (userInfo) {
          commit("setError", null);
          router.push("/");
        }
      })
      .catch((error) => commit("setError", error));
  },
  // TODO: ADICIONAR FORMA DE REDEFINIR SENHA
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
};

export default {
  // TODO: Verificar necessidade de uso do namespaced
  // namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
