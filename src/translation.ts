import { createI18n } from "vue-i18n/index";

const messages = {
  English: {
    logintitle1: "A PLACE FOR YOU TO ORGANIZE",
    logintitle2: "EVERYTHING YOU HAVE READ",
    signin: "Sign In",
    signup: "Sign Up",
    name: "Name",
    mail: "Email",
    password: "Password",
    menu: {
      home: "Home",
      add: "Add",
      friends: "Friends",
      settings: "Settings",
    },
    shelf: "{name}'s Bookcase",
    shelfname: "Shelf Name",
    search: "Search",
    addlabel: "Search by title, author, publisher, release, ISBN...",
    followfriends: "Follow Friends",
    message: {
      hello: "Hello World!",
      testing: "Below this line I'm just testing a few things.",
      counter: "Counter",
    },
  },
  Português: {
    logintitle1: "UM LUGAR PRA VOCÊ ORGANIZAR",
    logintitle2: "TUDO AQUILO QUE VOCÊ JÁ LEU",
    signin: "Entrar",
    signup: "Registrar",
    name: "Nome",
    mail: "E-mail",
    password: "Senha",
    menu: {
      home: "Início",
      add: "Adicionar",
      friends: "Amigos",
      settings: "Ajustes",
    },
    shelf: "Estante de {name}",
    shelfname: "Nome da Estante",
    search: "Buscar",
    addlabel: "Pesquise por título, autor, editora, lançamento, ISBN...",
    followfriends: "Seguir Amigos",
    message: {
      hello: "Olá Mundo!",
      testing: "Abaixo desta linha, estou apenas testando algumas coisas.",
      counter: "Contador",
    },
  },
  日本語: {
    logintitle1: "あなたが整理する場所",
    logintitle2: "あなたが読んだすべてのもの",
    signin: "ログイン",
    signup: "サインアップ",
    name: "名前",
    mail: "Eメール",
    password: "パスワード",
    menu: {
      home: "ホーム",
      add: "追加",
      friends: "友達",
      settings: "設定",
    },
    shelf: "{name} 本棚",
    shelfname: "棚名",
    search: "検索",
    addlabel: "タイトル、著者、発行者、リリース、ISBNで検索...",
    followfriends: "友達をフォローする",
    message: {
      hello: "こんにちは、世界",
      testing: "この線の下で、私はいくつかのことをテストしています。",
      counter: "カウンター",
    },
  },
};

// Create i18n instance with options
export const i18n = createI18n({
  locale: "English",
  fallbackLocale: ["Português", "日本語"],
  messages,
}
);
