import {
  createUserWithEmailAndPassword,
  getAdditionalUserInfo,
  GoogleAuthProvider,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signInWithPopup,
  signOut,
} from "firebase/auth";
import { doc, getDoc, setDoc, updateDoc } from "firebase/firestore";
import { auth, db } from "../../firebase";
import router from "../../router";

const state = {
  user: {},
};

const getters = {
  getUserProfile(state) {
    return state.user;
  },
  getUserID(state) {
    return state.user.uid;
  },
  getUserShelfName(state) {
    return state.user.shelfName;
  },
  isAuthenticated(state) {
    return state.user.email !== undefined;
  },
};

const mutations = {
  setUserProfile(state, val) {
    state.user = val;
  },
  setUserShelfName(state, val) {
    state.user.shelfName = val;
  },
};

const actions = {
  async login({ dispatch }, payload) {
    await signInWithEmailAndPassword(auth, payload.email, payload.password)
      .then((firebaseData) => dispatch("fetchUserProfile", firebaseData.user))
      .catch((error) => {
        throw error.code;
      });
  },
  async logout({ commit }) {
    await signOut(auth).then(() => {
      commit("setUserProfile", {});
      commit("clearBooks");
      commit("setModifiedAt", null);
      router.push("login");
    });
  },
  async signup({}, payload) {
    await createUserWithEmailAndPassword(auth, payload.email, payload.password)
      .then(async (userCredential) => {
        const userID = userCredential.user.uid;
        await setDoc(doc(db, "users", userID), {
          name: payload.name,
          email: payload.email,
          shelfName: payload.name,
        });
      })
      .catch((error) => {
        throw error.code;
      });
  },
  async googleSignIn({ dispatch }) {
    const provider = new GoogleAuthProvider();
    await signInWithPopup(auth, provider)
      .then(async (result) => {
        const { isNewUser } = getAdditionalUserInfo(result);
        if (isNewUser) {
          await setDoc(doc(db, "users", result.user.uid), {
            email: result.user.email,
            name: result.user.displayName,
            photoURL: result.user.photoURL,
            shelfName: result.user.displayName,
          });
        }
        dispatch("fetchUserProfile", result.user);
      })
      .catch((error) => {
        throw error.code;
      });
  },
  async fetchUserProfile({ commit }, user) {
    const userRef = doc(db, "users", user.uid);
    await getDoc(userRef)
      .then((firebaseData) => {
        const userInfo = firebaseData.data();
        userInfo.uid = firebaseData.id;
        commit("setUserProfile", (userInfo ??= {}));
        if (userInfo) router.push("/");
      })
      .catch((error) => console.error(error));
  },
  async resetPassword({}, payload) {
    await sendPasswordResetEmail(auth, payload.email).catch((error) => {
      throw error.code;
    });
  },
  async updateShelfName({ commit, rootGetters }, payload) {
    await updateDoc(doc(db, "users", rootGetters.getUserID), { shelfName: payload })
      .then(() => commit("setUserShelfName", payload))
      .catch((error) => console.error(error));
  },
};

export default {
  state,
  getters,
  mutations,
  actions,
};
