import { createI18n } from 'vue-i18n'
import en from './en.json'
import ja from './ja.json'
import pt from './pt.json'
import tr from './tr.json'

export const localeOptions = [
  { value: 'en', label: 'English' },
  { value: 'ja', label: '日本語' },
  { value: 'pt', label: 'Português' },
  { value: 'tr', label: 'Türkçe' }
] as const

export type SupportedLocale = (typeof localeOptions)[number]['value']

export const i18n = createI18n({
  allowComposition: true,
  fallbackLocale: ['en', 'pt', 'ja', 'tr'],
  fallbackWarn: false,
  legacy: false,
  locale: localeOptions.map((locale) => locale.value).includes(navigator.language as SupportedLocale)
    ? (navigator.language as SupportedLocale)
    : 'en',
  messages: { en, pt, ja, tr },
  missingWarn: false
})
