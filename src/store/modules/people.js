import { collection, doc, getDoc, getDocs, updateDoc } from "firebase/firestore";
import { db } from "../../firebase";

const state = {
  users: [],
  friends: [],
};

const getters = {
  getUser(state) {
    return (id) => state.users.find((user) => user.id === id);
  },
  getUsers(state) {
    return state.users;
  },
  getFriends(state) {
    return state.friends;
  },
  getUserBooks(state) {
    return (userId) => state.users.find((user) => user.id === userId).books;
  },
  getModifiedAt(state) {
    return (userId) => state.users.find((user) => user.id === userId)?.modifiedAt;
  },
};

const mutations = {
  setUser(state, val) {
    state.users = [...state.users].concat(val);
  },
  setUsers(state, users) {
    state.users = users.map(({ ...userDB }) => {
      let userLS = state.users.find((user) => user.id === userDB.id);
      return { ...userLS, ...userDB };
    }, {});
  },
  setUserBooks(state, { userID, userBooks }) {
    state.users.find((user) => user.id === userID).books = userBooks;
  },
  setFriends(state, friends) {
    state.friends = friends;
  },
  setUserModifiedAt(state, { userID, currentDate }) {
    state.users.find((user) => user.id === userID).modifiedAt = currentDate;
  },
};

const actions = {
  async queryDBUser({ dispatch, rootGetters }, userID) {
    const user = await getDoc(doc(db, "users", userID));
    if ((user.data().books === undefined) | (user.data().modifiedAt !== rootGetters.getModifiedAt(userID))) {
      dispatch("queryDBUserBooks", userID);
    }
  },

  async queryDBUsers({ commit }) {
    await getDocs(collection(db, "users"))
      .then((querySnapshot) => {
        const users = querySnapshot.docs.map((doc) => ({ id: doc.id, ...doc.data() }));
        commit("setUsers", users);
      })
      .catch((error) => console.error(error));
  },

  async queryDBUserBooks({ commit }, userID) {
    await getDocs(collection(db, "users", userID, "books"))
      .then((querySnapshot) => {
        const books = querySnapshot.docs.map((doc) => doc.data());
        const payload = { userID, userBooks: books };
        commit("setUserBooks", payload);
      })
      .catch((error) => console.error(error));
  },

  async modifiedAt({ commit, rootGetters }, userID) {
    const currentDate = Date.now();
    await updateDoc(doc(db, "users", rootGetters.getMyID), { modifiedAt: currentDate })
      .then(() => commit("setUserModifiedAt", { userID, currentDate }))
      .catch((error) => console.error(error));
  },

  async compareUserModifiedAt({ commit, rootGetters }, userID) {
    const LSModifiedAt = rootGetters.getModifiedAt(userID) || 0;
    const DBModifiedAt = await getDoc(doc(db, "users", userID)).then((doc) => doc.data().modifiedAt);
    commit("setUserModifiedAt", { userID, currentDate: DBModifiedAt });
    return Boolean(DBModifiedAt === LSModifiedAt);
  },
  async checkUsername({}, username) {
    const users = await getDocs(collection(db, "users"));
    const usernames = users.docs.map((doc) => doc.data().username);
    return Boolean(usernames.includes(username));
  },
};

export default {
  state,
  getters,
  mutations,
  actions,
};
