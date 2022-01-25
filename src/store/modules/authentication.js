import {
  createUserWithEmailAndPassword,
  EmailAuthProvider,
  getAdditionalUserInfo,
  GoogleAuthProvider,
  reauthenticateWithCredential,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signInWithPopup,
  signOut,
  updateEmail,
  updatePassword,
} from "firebase/auth";
import { doc, getDoc, runTransaction, setDoc, updateDoc } from "firebase/firestore";
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
  getMyEmail(state) {
    return state.user.email;
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
  setMyEmail(state, val) {
    state.user.email = val;
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

  logout({ commit }) {
    signOut(auth);
    commit("setUserProfile", {});
    commit("clearBooks");
    router.push("/login");
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
        if (isNewUser) {
          const username = email.split("@")[0] + Date.now();
          await setDoc(doc(db, "users", uid), { email, displayName, photoURL, username });
        }
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

  async resetPassword({}, email) {
    await sendPasswordResetEmail(auth, email).catch((error) => {
      throw error.code;
    });
  },

  async updateAccount({ commit, rootGetters }, payload) {
    const user = auth.currentUser;
    const credential = EmailAuthProvider.credential(user.email, payload.password);

    await reauthenticateWithCredential(user, credential)
      .then(async () => {
        await updateDoc(doc(db, "users", rootGetters.getMyID), { email: payload.email });
        updateEmail(user, payload.email).then(() => commit("setMyEmail", payload.email));
        updatePassword(user, payload.newPass);
      })
      .catch((error) => {
        throw error.code;
      });
  },

  async updateProfile({ commit, rootGetters }, payload) {
    await runTransaction(db, async (transaction) => {
      transaction.update(doc(db, "users", rootGetters.getMyID), { ...payload });
    }).then(() => {
      commit("setMyDisplayName", payload.displayName);
      commit("setMyUsername", payload.username);
    });
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
