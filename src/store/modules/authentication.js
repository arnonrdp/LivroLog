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
  getMyProfile(state) {
    return state.user;
  },
  getMyID(state) {
    return state.user.uid;
  },
  getMyShelfName(state) {
    return state.user.shelfName;
  },
  getMyModifiedAt(state) {
    return state.user.modifiedAt;
  },
  isAuthenticated(state) {
    return state.user.email !== undefined;
  },
};

const mutations = {
  setUserProfile(state, val) {
    state.user = val;
  },
  setMyShelfName(state, val) {
    state.user.shelfName = val;
  },
  setMyModifiedAt(state, val) {
    state.user.modifiedAt = val;
  },
};

const actions = {
  async login({ dispatch }, payload) {
    await signInWithEmailAndPassword(auth, payload.email, payload.password)
      .then((userCredential) => dispatch("fetchUserProfile", userCredential.user))
      .catch((error) => {
        throw error.code;
      });
  },

  async logout({ commit }) {
    await signOut(auth).then(() => {
      commit("setUserProfile", {});
      commit("clearBooks");
    });
    router.push("login");
  },

  async signup({ dispatch }, payload) {
    await createUserWithEmailAndPassword(auth, payload.email, payload.password)
      .then(async (userCredential) => {
        await setDoc(doc(db, "users", userCredential.user.uid), { ...payload }).then(() =>
          dispatch("fetchUserProfile", userCredential.user),
        );
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
    await getDoc(doc(db, "users", user.uid))
      .then((doc) => {
        const userInfo = { uid: doc.id, ...doc.data() };
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
    await updateDoc(doc(db, "users", rootGetters.getMyID), { shelfName: payload })
      .then(() => commit("setMyShelfName", payload))
      .catch((error) => console.error(error));
  },

  async compareMyModifiedAt({ commit, rootGetters }) {
    const LSModifiedAt = rootGetters.getMyModifiedAt;
    const DBModifiedAt = await getDoc(doc(db, "users", rootGetters.getMyID)).then((doc) => doc.data().modifiedAt);
    commit("setMyModifiedAt", DBModifiedAt);
    return Boolean(LSModifiedAt === DBModifiedAt);
  },
};

export default {
  state,
  getters,
  mutations,
  actions,
};
