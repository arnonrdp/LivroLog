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
  getMyUsername(state) {
    return state.user.username;
  },
  getMyDisplayName(state) {
    return state.user.displayName;
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
  setMyUsername(state, val) {
    state.user.username = val;
  },
  setMyDisplayName(state, val) {
    state.user.displayName = val;
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
    await signOut(auth);
    commit("setUserProfile", {});
    commit("clearBooks");
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
        const { email, displayName, photoURL, uid } = result.user;
        if (isNewUser) await setDoc(doc(db, "users", uid), { email, displayName, photoURL });
        dispatch("fetchUserProfile", { email, displayName, photoURL, uid });
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

  async updateUsername({ commit, rootGetters }, payload) {
    await updateDoc(doc(db, "users", rootGetters.getMyID), { username: payload })
      .then(() => commit("setMyUsername", payload))
      .catch((error) => console.error(error));
  },

  async updateDisplayName({ commit, rootGetters }, payload) {
    await updateDoc(doc(db, "users", rootGetters.getMyID), { displayName: payload })
      .then(() => commit("setMyDisplayName", payload))
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
