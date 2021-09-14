import firebase from "firebase/app";
import "firebase/storage";
import firebaseConfig from "./main";

///Initialize Firebase///
firebase.initializeApp(firebaseConfig);

///Utils///
const fb = firebase;
const storageRef = firebase.storage().ref();

export { fb, storageRef };
