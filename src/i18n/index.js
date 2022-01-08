import { createI18n } from "vue-i18n";
import en from "./en.json";
import ja from "./ja.json";
import pt from "./pt-br.json";

const messages = {
  en: en,
  pt: pt,
  ja: ja,
};

export const i18n = createI18n({
  locale: navigator.language || "en",
  fallbackLocale: ["en", "pt", "ja"],
  silentTranslationWarn: true,
  silentFallbackWarn: true,
  messages,
});
