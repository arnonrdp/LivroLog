export interface AmazonRegionConfig {
  domain: string
  tag: string
  language: string
}

export const AMAZON_REGIONS: Record<string, AmazonRegionConfig> = {
  BR: {
    domain: 'amazon.com.br',
    tag: 'livrolog01-20', // Official Brazil Associates tag
    language: 'pt-BR'
  },
  US: {
    domain: 'amazon.com',
    tag: 'livrolog-20', // US Associates tag (if you have one)
    language: 'en-US'
  },
  UK: {
    domain: 'amazon.co.uk',
    tag: 'livrolog-20', // Use registered tag or create regional ones later
    language: 'en-GB'
  },
  DE: {
    domain: 'amazon.de',
    tag: 'livrolog-20', // Use registered tag or create regional ones later
    language: 'de-DE'
  },
  CA: {
    domain: 'amazon.ca',
    tag: 'livrolog-20',
    language: 'en-CA'
  },
  FR: {
    domain: 'amazon.fr',
    tag: 'livrolog-20', // Use registered tag or create regional ones later
    language: 'fr-FR'
  },
  IT: {
    domain: 'amazon.it',
    tag: 'livrolog-20', // Use registered tag or create regional ones later
    language: 'it-IT'
  },
  ES: {
    domain: 'amazon.es',
    tag: 'livrolog-20', // Use registered tag or create regional ones later
    language: 'es-ES'
  }
}

export const DEFAULT_REGION = 'US'

/**
 * Get Amazon region configuration based on locale
 */
export function getAmazonRegionConfig(locale: string): AmazonRegionConfig {
  const lowerLocale = locale.toLowerCase()

  // Map locales to regions
  const localeToRegion: Record<string, string> = {
    'pt-br': 'BR', // Brazil has its own Amazon Associates program
    pt_br: 'BR',
    'en-gb': 'UK',
    en_gb: 'UK',
    'en-ca': 'CA',
    en_ca: 'CA',
    'de-de': 'DE',
    de_de: 'DE',
    'fr-fr': 'FR',
    fr_fr: 'FR',
    'it-it': 'IT',
    it_it: 'IT',
    'es-es': 'ES',
    es_es: 'ES'
  }

  // Find matching region
  const region = Object.keys(localeToRegion).find((key) => lowerLocale.startsWith(key))

  const regionCode = region ? localeToRegion[region] : DEFAULT_REGION
  return AMAZON_REGIONS[regionCode as keyof typeof AMAZON_REGIONS] || AMAZON_REGIONS[DEFAULT_REGION]!
}

/**
 * Get Amazon search URL for region
 */
export function getAmazonSearchUrl(domain: string): string {
  return `https://www.${domain}/s`
}
