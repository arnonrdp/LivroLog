import { createApp } from "vue";
import { createStore } from "vuex";
import { auth, db } from "@/firebase";
import { collection, doc, getDoc, getDocs, query, setDoc, arrayUnion, updateDoc, runTransaction } from "firebase/firestore";

const store = createStore({
  state() {
    return {
      count: 0,
      books: [],
      shelfName: null,
      booksApi: {},
      index: 0
    };
  },
  mutations: {
    async getShelfName(state) {
      const userID = auth.currentUser.uid;
      const userRef = doc(db, "users", userID);
      const userSnap = await getDoc(userRef);
      state.shelfName = userSnap.data().shelfName;
    },
    increment(state) {
      state.count++;
    },
    search(state, seek) {
      state.booksApi = {};
      // TODO: CALL API IN PROD => &key=${API}
      // const API = "AIzaSyAJGXLBDW269OHGuSblb0FTg80EmdLLdBQ";
      fetch(
        `https://www.googleapis.com/books/v1/volumes?q=${seek}&maxResults=40&printType=books`
      )
        .then((response) => response.json())
        .then((data) => {
          state.booksApi = data.items.map((item) => ({
            id: item.id,
            title: item.volumeInfo.title,
            authors: item.volumeInfo.authors,
            thumbnail: item.volumeInfo.imageLinks?.thumbnail,
          }));
          // TODO: LOADING BAR WHILE LIST IS BEING LOADED
        });
    },
    async add(state, { bookID, title, authors, thumbnail }) {
      const userID = auth.currentUser.uid;
      const booksRef = doc(db, "books", bookID);

      try {
        await runTransaction(db, async (transaction) => {
          const sfDoc = await transaction.get(booksRef);
          if (!sfDoc.exists()) {
            setDoc(doc(db, "books", bookID), {
              title: title,
              authors: authors,
              thumbnail: thumbnail,
              readers: arrayUnion(userID),
            });
          } else {
            await updateDoc(booksRef, {
              readers: arrayUnion(userID),
            });
          }
        });
      } catch (e) {
        console.error("ERRO: ", e);
      } finally {
        await setDoc(doc(db, "users", userID, "addedBooks", bookID), {
          bookRef: booksRef,
          addedIn: new Date(),
          readIn: "August",
        });
      }
    },
    async updateShelfName(state, newShelfName) {
      const userID = auth.currentUser.uid;
      const userRef = doc(db, "users", userID);

      await updateDoc(userRef, {
        shelfName: newShelfName,
      })
    },
  },
  actions: {
    increment: ({ commit }) => commit("increment"),
  },
  getters: {
    async getBooks(state) {

      state.books = []
      const userID = auth.currentUser.uid;
      const userRef = doc(db, "users", userID);
      const userSnap = await getDoc(userRef);

      state.shelfName = userSnap.data().shelfName;

      const userBooksRef = query(collection(db, "users", userID, "addedBooks"));
      const querySnapshot = await getDocs(userBooksRef);

      await querySnapshot.forEach((doc) => {
        state.books.push({
          id: doc.id,
          addedIn: doc.data().addedIn,
          readIn: doc.data().readIn,
        });
      });

      await state.books.map(async (book) => {
        const booksRef = doc(db, "books", book.id);
        const bookSnap = await getDoc(booksRef);

        book.authors = bookSnap.data().authors;
        book.title = bookSnap.data().title;
        book.thumbnail = bookSnap.data().thumbnail;
      });
      state.index++;
    }
  },
});

store.dispatch("increment");

app.use(store);

export default store;
