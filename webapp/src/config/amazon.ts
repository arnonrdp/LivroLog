export interface AmazonRegionConfig {
  domain: string
  language: string
}

export interface AmazonStore {
  code: string
  domain: string
  labelKey: string
  group: 'americas' | 'europe' | 'asia-pacific' | 'middle-east-africa'
}

export const AMAZON_REGIONS: Record<string, AmazonRegionConfig> = {
  // Americas
  US: {
    domain: 'amazon.com',
    language: 'en-US'
  },
  CA: {
    domain: 'amazon.ca',
    language: 'en-CA'
  },
  MX: {
    domain: 'amazon.com.mx',
    language: 'es-MX'
  },
  BR: {
    domain: 'amazon.com.br',
    language: 'pt-BR'
  },
  // Europe
  UK: {
    domain: 'amazon.co.uk',
    language: 'en-GB'
  },
  DE: {
    domain: 'amazon.de',
    language: 'de-DE'
  },
  FR: {
    domain: 'amazon.fr',
    language: 'fr-FR'
  },
  IT: {
    domain: 'amazon.it',
    language: 'it-IT'
  },
  ES: {
    domain: 'amazon.es',
    language: 'es-ES'
  },
  NL: {
    domain: 'amazon.nl',
    language: 'nl-NL'
  },
  SE: {
    domain: 'amazon.se',
    language: 'sv-SE'
  },
  PL: {
    domain: 'amazon.pl',
    language: 'pl-PL'
  },
  BE: {
    domain: 'amazon.com.be',
    language: 'nl-BE'
  },
  TR: {
    domain: 'amazon.com.tr',
    language: 'tr-TR'
  },
  IE: {
    domain: 'amazon.ie',
    language: 'en-IE'
  },
  // Asia-Pacific
  JP: {
    domain: 'amazon.co.jp',
    language: 'ja-JP'
  },
  IN: {
    domain: 'amazon.in',
    language: 'en-IN'
  },
  AU: {
    domain: 'amazon.com.au',
    language: 'en-AU'
  },
  SG: {
    domain: 'amazon.sg',
    language: 'en-SG'
  },
  // Middle East & Africa
  AE: {
    domain: 'amazon.ae',
    language: 'en-AE'
  },
  SA: {
    domain: 'amazon.sa',
    language: 'ar-SA'
  },
  EG: {
    domain: 'amazon.eg',
    language: 'ar-EG'
  },
  ZA: {
    domain: 'amazon.co.za',
    language: 'en-ZA'
  }
}

export const AMAZON_STORES: AmazonStore[] = [
  // Americas
  { code: 'US', domain: 'amazon.com', labelKey: 'amazon-store-us', group: 'americas' },
  { code: 'CA', domain: 'amazon.ca', labelKey: 'amazon-store-ca', group: 'americas' },
  { code: 'MX', domain: 'amazon.com.mx', labelKey: 'amazon-store-mx', group: 'americas' },
  { code: 'BR', domain: 'amazon.com.br', labelKey: 'amazon-store-br', group: 'americas' },
  // Europe
  { code: 'UK', domain: 'amazon.co.uk', labelKey: 'amazon-store-uk', group: 'europe' },
  { code: 'DE', domain: 'amazon.de', labelKey: 'amazon-store-de', group: 'europe' },
  { code: 'FR', domain: 'amazon.fr', labelKey: 'amazon-store-fr', group: 'europe' },
  { code: 'IT', domain: 'amazon.it', labelKey: 'amazon-store-it', group: 'europe' },
  { code: 'ES', domain: 'amazon.es', labelKey: 'amazon-store-es', group: 'europe' },
  { code: 'NL', domain: 'amazon.nl', labelKey: 'amazon-store-nl', group: 'europe' },
  { code: 'SE', domain: 'amazon.se', labelKey: 'amazon-store-se', group: 'europe' },
  { code: 'PL', domain: 'amazon.pl', labelKey: 'amazon-store-pl', group: 'europe' },
  { code: 'BE', domain: 'amazon.com.be', labelKey: 'amazon-store-be', group: 'europe' },
  { code: 'TR', domain: 'amazon.com.tr', labelKey: 'amazon-store-tr', group: 'europe' },
  { code: 'IE', domain: 'amazon.ie', labelKey: 'amazon-store-ie', group: 'europe' },
  // Asia-Pacific
  { code: 'JP', domain: 'amazon.co.jp', labelKey: 'amazon-store-jp', group: 'asia-pacific' },
  { code: 'IN', domain: 'amazon.in', labelKey: 'amazon-store-in', group: 'asia-pacific' },
  { code: 'AU', domain: 'amazon.com.au', labelKey: 'amazon-store-au', group: 'asia-pacific' },
  { code: 'SG', domain: 'amazon.sg', labelKey: 'amazon-store-sg', group: 'asia-pacific' },
  // Middle East & Africa
  { code: 'AE', domain: 'amazon.ae', labelKey: 'amazon-store-ae', group: 'middle-east-africa' },
  { code: 'SA', domain: 'amazon.sa', labelKey: 'amazon-store-sa', group: 'middle-east-africa' },
  { code: 'EG', domain: 'amazon.eg', labelKey: 'amazon-store-eg', group: 'middle-east-africa' },
  { code: 'ZA', domain: 'amazon.co.za', labelKey: 'amazon-store-za', group: 'middle-east-africa' }
]

export const AMAZON_STORE_GROUPS = {
  americas: 'amazon-group-americas',
  europe: 'amazon-group-europe',
  'asia-pacific': 'amazon-group-asia-pacific',
  'middle-east-africa': 'amazon-group-middle-east-africa'
} as const

export const DEFAULT_REGION = 'BR'

const LOCALE_TO_REGION: Record<string, string> = {
  // Americas
  'pt-br': 'BR',
  pt_br: 'BR',
  pt: 'BR',
  'en-us': 'US',
  en_us: 'US',
  'en-ca': 'CA',
  en_ca: 'CA',
  'es-mx': 'MX',
  es_mx: 'MX',
  // Europe
  'en-gb': 'UK',
  en_gb: 'UK',
  'de-de': 'DE',
  de_de: 'DE',
  'fr-fr': 'FR',
  fr_fr: 'FR',
  'it-it': 'IT',
  it_it: 'IT',
  'es-es': 'ES',
  es_es: 'ES',
  'nl-nl': 'NL',
  nl_nl: 'NL',
  'sv-se': 'SE',
  sv_se: 'SE',
  'pl-pl': 'PL',
  pl_pl: 'PL',
  'nl-be': 'BE',
  nl_be: 'BE',
  'tr-tr': 'TR',
  tr_tr: 'TR',
  'en-ie': 'IE',
  en_ie: 'IE',
  // Asia-Pacific
  'ja-jp': 'JP',
  ja_jp: 'JP',
  ja: 'JP',
  'en-in': 'IN',
  en_in: 'IN',
  'hi-in': 'IN',
  hi_in: 'IN',
  'en-au': 'AU',
  en_au: 'AU',
  'en-sg': 'SG',
  en_sg: 'SG',
  // Middle East & Africa
  'ar-ae': 'AE',
  ar_ae: 'AE',
  'en-ae': 'AE',
  en_ae: 'AE',
  'ar-sa': 'SA',
  ar_sa: 'SA',
  'ar-eg': 'EG',
  ar_eg: 'EG',
  'en-za': 'ZA',
  en_za: 'ZA'
}

/**
 * Map a locale string to an Amazon region code.
 */
export function getAmazonRegionCode(locale: string): string {
  const lower = locale.toLowerCase()
  const match = Object.keys(LOCALE_TO_REGION).find((key) => lower.startsWith(key))

  return match ? LOCALE_TO_REGION[match]! : DEFAULT_REGION
}

/**
 * Decide which Amazon store to open for the current viewer.
 *
 * The store saved in Settings always wins. Only when none is set do we guess from the
 * browser language, and BR is the last resort because it is the one marketplace whose
 * affiliate tag belongs to us.
 */
export function resolvePreferredRegion(userRegion?: string | null): string {
  if (userRegion && userRegion in AMAZON_REGIONS) return userRegion

  const browserLocale = typeof navigator === 'undefined' ? '' : navigator.language

  return browserLocale ? getAmazonRegionCode(browserLocale) : DEFAULT_REGION
}

/**
 * Get Amazon region configuration based on locale
 */
export function getAmazonRegionConfig(locale: string): AmazonRegionConfig {
  return AMAZON_REGIONS[getAmazonRegionCode(locale)] ?? AMAZON_REGIONS[DEFAULT_REGION]!
}

/**
 * Get Amazon search URL for region
 */
export function getAmazonSearchUrl(domain: string): string {
  return `https://www.${domain}/s`
}

/**
 * Get Amazon store by code
 */
export function getAmazonStore(code: string): AmazonStore | undefined {
  return AMAZON_STORES.find((store) => store.code === code)
}
