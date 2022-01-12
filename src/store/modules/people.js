import { collection, doc, getDoc, getDocs } from "firebase/firestore";
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
    return (userId) => state.users.find((user) => user.id === userId).modifiedAt;
  },
};

const mutations = {
  setUsers(state, users) {
    // TODO: Verificar se os livros permacem dentro do objeto user
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
  setModifiedAt(state, val) {
    state.modifiedAt = val;
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
        const users = querySnapshot.docs.map((doc) => Object.assign({ id: doc.id }, doc.data()));
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

  async modifiedAt({ commit, rootGetters }) {
    const currentDate = Date.now();
    await updateDoc(doc(db, "users", rootGetters.getUserID), { modifiedAt: currentDate })
      .then(() => commit("setModifiedAt", currentDate))
      .catch((error) => console.error(error));
  },

  async queryModifiedAt({ commit }, userID) {
    await getDoc(doc(db, "users", userID))
      .then((doc) => {
        const modifiedAt = doc.data().modifiedAt;
        commit("setModifiedAt", modifiedAt);
      })
      .catch((error) => console.error(error));
  },
};

export default {
  state,
  getters,
  mutations,
  actions,
};
