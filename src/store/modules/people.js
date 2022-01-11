import { collection, doc, getDocs } from "firebase/firestore";
import { db } from "../../firebase";

const state = {
  users: [],
  friends: [],
  lastSignAt: null,
};

const getters = {
  getUsers: (state) => state.users,
  getUserBooks: (state) => (userId) => state.users.find((user) => user.id === userId).books,
  getFriends: (state) => state.friends,
  getLastSignAt: (state) => state.lastSignAt,
};

const mutations = {
  setUsers(state, users) {
    state.users = users;
  },
  setUserBooks(state, { userID, userBooks }) {
    state.users.find((user) => user.id === userID).books = userBooks;
  },
  setFriends(state, friends) {
    state.friends = friends;
  },
  setLastSignAt(state, lastSignAt) {
    state.lastSignAt = lastSignAt;
  },
};

const actions = {
  async queryUsersFromDB({ commit }) {
    let users = [];
    await getDocs(collection(db, "users"))
      .then((querySnapshot) => {
        users = querySnapshot.docs.map((doc) => Object.assign({ id: doc.id }, doc.data()));
        commit("setUsers", users);
      })
      .catch((error) => console.error(error));
  },
  async queryBooksFromUser({ commit }, userID) {
    await getDocs(collection(db, "users", userID, "books"))
      .then((querySnapshot) => {
        const books = querySnapshot.docs.map((doc) => doc.data());
        const payload = { userID, userBooks: books };
        commit("setUserBooks", payload);
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
