import { createI18n } from "vue-i18n";
import en from "./en.json";
import ja from "./ja.json";
import pt from "./pt-br.json";
import tr from "./tr.json";

const messages = { en, pt, ja, tr };

export const i18n = createI18n({
  locale: navigator.language || "en",
  fallbackLocale: ["en", "pt", "ja", "tr"],
  silentTranslationWarn: true,
  silentFallbackWarn: true,
  messages,
});
