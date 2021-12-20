import { createI18n } from "vue-i18n/index";
import en from "./en.json";
import ja from "./ja.json";
import pt from "./pt-br.json";

const messages = {
  "English": en,
  "Português": pt,
  "日本語": ja,
};

// Create i18n instance with options
export const i18n = createI18n({
  locale: "English",
  fallbackLocale: ["Português", "日本語"],
  messages,
});
