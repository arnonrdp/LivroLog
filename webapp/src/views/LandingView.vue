<template>
  <q-page class="landing-page">
    <!-- Hero Section -->
    <section class="hero-section">
      <div class="hero-background"></div>
      <div class="hero-content">
        <div class="hero-badge">
          <q-icon name="auto_stories" size="1rem" />
          <span>{{ $t('landing.badge') }}</span>
        </div>
        <h1 class="hero-title">{{ $t('landing.hero-title') }}</h1>
        <p class="hero-subtitle">{{ $t('landing.hero-subtitle') }}</p>

        <!-- Search Box -->
        <div class="search-box">
          <q-input
            v-model="searchQuery"
            bg-color="white"
            color="amber-8"
            outlined
            :placeholder="$t('landing.search-placeholder')"
            rounded
            @keydown.enter="handleSearch"
          >
            <template #append>
              <q-btn
                color="amber-8"
                icon="search"
                padding="sm"
                round
                size="md"
                unelevated
                @click="handleSearch"
              />
            </template>
          </q-input>
        </div>

        <p class="hero-cta-text">{{ $t('landing.cta-text') }}</p>

        <!-- Trust indicators -->
        <div class="trust-indicators">
          <div class="trust-item">
            <q-icon name="verified" size="1.25rem" />
            <span>{{ $t('landing.trust-free') }}</span>
          </div>
          <div class="trust-item">
            <q-icon name="lock" size="1.25rem" />
            <span>{{ $t('landing.trust-private') }}</span>
          </div>
          <div class="trust-item">
            <q-icon name="speed" size="1.25rem" />
            <span>{{ $t('landing.trust-fast') }}</span>
          </div>
        </div>
      </div>

      <!-- Decorative books -->
      <div class="hero-decoration">
        <div class="floating-book book-1">
          <q-icon name="menu_book" size="3rem" />
        </div>
        <div class="floating-book book-2">
          <q-icon name="auto_stories" size="2.5rem" />
        </div>
        <div class="floating-book book-3">
          <q-icon name="book" size="2rem" />
        </div>
      </div>
    </section>

    <!-- Social Proof Section -->
    <section class="social-proof-section">
      <div class="stats-container">
        <div class="stat-item">
          <span class="stat-number">{{ formatNumber(stats.books) }}+</span>
          <span class="stat-label">{{ $t('landing.stats-books') }}</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">{{ formatNumber(stats.readers) }}+</span>
          <span class="stat-label">{{ $t('landing.stats-readers') }}</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">{{ formatNumber(stats.reviews) }}+</span>
          <span class="stat-label">{{ $t('landing.stats-reviews') }}</span>
        </div>
      </div>
    </section>

    <!-- How it Works Section -->
    <section class="how-it-works-section">
      <div class="section-header">
        <h2>{{ $t('landing.how-title') }}</h2>
        <p>{{ $t('landing.how-subtitle') }}</p>
      </div>
      <div class="steps-container">
        <div class="step-card">
          <div class="step-number">1</div>
          <div class="step-icon">
            <q-icon name="search" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.step1-title') }}</h3>
          <p>{{ $t('landing.step1-desc') }}</p>
        </div>
        <div class="step-arrow">
          <q-icon name="arrow_forward" size="2rem" />
        </div>
        <div class="step-card">
          <div class="step-number">2</div>
          <div class="step-icon">
            <q-icon name="library_add" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.step2-title') }}</h3>
          <p>{{ $t('landing.step2-desc') }}</p>
        </div>
        <div class="step-arrow">
          <q-icon name="arrow_forward" size="2rem" />
        </div>
        <div class="step-card">
          <div class="step-number">3</div>
          <div class="step-icon">
            <q-icon name="people" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.step3-title') }}</h3>
          <p>{{ $t('landing.step3-desc') }}</p>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
      <div class="section-header">
        <h2>{{ $t('landing.features-title') }}</h2>
        <p>{{ $t('landing.features-subtitle') }}</p>
      </div>
      <div class="features-container">
        <div class="feature-card">
          <div class="feature-icon">
            <q-icon name="auto_stories" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.feature-organize-title') }}</h3>
          <p>{{ $t('landing.feature-organize-desc') }}</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <q-icon name="star" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.feature-rate-title') }}</h3>
          <p>{{ $t('landing.feature-rate-desc') }}</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <q-icon name="people" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.feature-connect-title') }}</h3>
          <p>{{ $t('landing.feature-connect-desc') }}</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <q-icon name="file_download" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.feature-import-title') }}</h3>
          <p>{{ $t('landing.feature-import-desc') }}</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <q-icon name="lock" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.feature-privacy-title') }}</h3>
          <p>{{ $t('landing.feature-privacy-desc') }}</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <q-icon name="shopping_cart" size="2.5rem" />
          </div>
          <h3>{{ $t('landing.feature-amazon-title') }}</h3>
          <p>{{ $t('landing.feature-amazon-desc') }}</p>
        </div>
      </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section">
      <div class="section-header light">
        <h2>{{ $t('landing.testimonials-title') }}</h2>
        <p>{{ $t('landing.testimonials-subtitle') }}</p>
      </div>
      <div class="testimonials-container">
        <div class="testimonial-card">
          <div class="testimonial-quote">
            <q-icon name="format_quote" size="2rem" />
          </div>
          <p class="testimonial-text">{{ $t('landing.testimonial1-text') }}</p>
          <div class="testimonial-author">
            <div class="author-avatar">
              <q-icon name="person" size="1.5rem" />
            </div>
            <div class="author-info">
              <span class="author-name">{{ $t('landing.testimonial1-author') }}</span>
              <span class="author-books">{{ $t('landing.testimonial1-books') }}</span>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-quote">
            <q-icon name="format_quote" size="2rem" />
          </div>
          <p class="testimonial-text">{{ $t('landing.testimonial2-text') }}</p>
          <div class="testimonial-author">
            <div class="author-avatar">
              <q-icon name="person" size="1.5rem" />
            </div>
            <div class="author-info">
              <span class="author-name">{{ $t('landing.testimonial2-author') }}</span>
              <span class="author-books">{{ $t('landing.testimonial2-books') }}</span>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-quote">
            <q-icon name="format_quote" size="2rem" />
          </div>
          <p class="testimonial-text">{{ $t('landing.testimonial3-text') }}</p>
          <div class="testimonial-author">
            <div class="author-avatar">
              <q-icon name="person" size="1.5rem" />
            </div>
            <div class="author-info">
              <span class="author-name">{{ $t('landing.testimonial3-author') }}</span>
              <span class="author-books">{{ $t('landing.testimonial3-books') }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="cta-content">
        <h2>{{ $t('landing.cta-title') }}</h2>
        <p>{{ $t('landing.cta-subtitle') }}</p>
        <div class="cta-buttons">
          <q-btn
            class="cta-primary-btn"
            color="amber-8"
            :label="$t('landing.cta-signup')"
            no-caps
            padding="md xl"
            rounded
            size="lg"
            text-color="white"
            unelevated
            @click="openRegister"
          />
          <q-btn
            class="cta-secondary-btn"
            :label="$t('landing.cta-explore')"
            no-caps
            outline
            padding="md xl"
            rounded
            size="lg"
            text-color="white"
            to="/search"
          />
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
      <div class="footer-content">
        <div class="footer-brand">
          <img alt="LivroLog" class="footer-logo" src="/logo.svg" />
          <p>{{ $t('landing.footer-tagline') }}</p>
        </div>
        <div class="footer-links">
          <router-link to="/search">{{ $t('landing.cta-explore') }}</router-link>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; {{ new Date().getFullYear() }} LivroLog. {{ $t('landing.footer-rights') }}</p>
      </div>
    </footer>
  </q-page>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores'
import { useMeta } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const baseUrl = import.meta.env.VITE_FRONTEND_URL || 'https://livrolog.com'

// SEO Meta Tags
useMeta(() => ({
  title: 'LivroLog - ' + t('landing.hero-title'),
  meta: {
    description: { name: 'description', content: t('landing.hero-subtitle') },
    ogType: { property: 'og:type', content: 'website' },
    ogTitle: { property: 'og:title', content: 'LivroLog - ' + t('landing.hero-title') },
    ogDescription: { property: 'og:description', content: t('landing.hero-subtitle') },
    ogImage: { property: 'og:image', content: `${baseUrl}/screenshot-web.jpg` },
    ogUrl: { property: 'og:url', content: baseUrl },
    twitterCard: { name: 'twitter:card', content: 'summary_large_image' },
    twitterTitle: { name: 'twitter:title', content: 'LivroLog - ' + t('landing.hero-title') },
    twitterDescription: { name: 'twitter:description', content: t('landing.hero-subtitle') }
  }
}))

const searchQuery = ref('')

// Simulated stats (could be fetched from API)
const stats = ref({
  books: 50000,
  readers: 1200,
  reviews: 3500
})

function formatNumber(num: number): string {
  if (num >= 1000) {
    return (num / 1000).toFixed(num % 1000 === 0 ? 0 : 1) + 'k'
  }
  return num.toString()
}

function handleSearch() {
  if (searchQuery.value.trim()) {
    router.push({ path: '/search', query: { q: searchQuery.value.trim() } })
  }
}

function openRegister() {
  authStore.openAuthModal('register')
}
</script>

<style scoped lang="sass">
// Warm Literary Color Palette
$cream: #FDF8F3
$warm-white: #FFFAF5
$paper: #F5EDE4
$amber: #D4A574
$amber-dark: #B8956A
$burgundy: #8B3A3A
$burgundy-light: #A94A4A
$forest: #2D4A3E
$charcoal: #2C2C2C
$text-primary: #3D3D3D
$text-secondary: #6B6B6B
$gold: #C9A962

.landing-page
  background: $cream
  overflow-x: hidden

// Hero Section
.hero-section
  align-items: center
  background: linear-gradient(180deg, $warm-white 0%, $cream 100%)
  display: flex
  justify-content: center
  min-height: calc(100vh - 60px)
  padding: 3rem 2rem
  position: relative
  text-align: center

.hero-background
  background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4a574' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")
  bottom: 0
  left: 0
  opacity: 0.5
  position: absolute
  right: 0
  top: 0

.hero-content
  max-width: 800px
  position: relative
  z-index: 1

.hero-badge
  align-items: center
  background: linear-gradient(135deg, $amber 0%, $amber-dark 100%)
  border-radius: 20px
  color: white
  display: inline-flex
  font-size: 0.85rem
  font-weight: 600
  gap: 0.5rem
  margin-bottom: 1.5rem
  padding: 0.5rem 1rem
  text-transform: uppercase

.hero-title
  color: $charcoal
  font-family: 'Georgia', serif
  font-size: clamp(2.2rem, 5vw, 3.8rem)
  font-weight: 700
  line-height: 1.15
  margin-bottom: 1rem

.hero-subtitle
  color: $text-secondary
  font-size: clamp(1.1rem, 2.5vw, 1.4rem)
  line-height: 1.5
  margin-bottom: 2.5rem

.search-box
  margin: 0 auto 1.5rem
  max-width: 550px

.hero-cta-text
  color: $text-secondary
  font-size: 0.95rem
  margin-bottom: 2rem

.trust-indicators
  display: flex
  flex-wrap: wrap
  gap: 1.5rem
  justify-content: center

.trust-item
  align-items: center
  color: $text-secondary
  display: flex
  font-size: 0.9rem
  gap: 0.4rem
  :deep(.q-icon)
    color: $forest

// Floating decorations
.hero-decoration
  display: none
  @media screen and (min-width: 1024px)
    display: block

.floating-book
  color: rgba($amber, 0.2)
  position: absolute
  animation: float 6s ease-in-out infinite

.book-1
  right: 10%
  top: 20%
  animation-delay: 0s

.book-2
  left: 8%
  top: 30%
  animation-delay: 2s

.book-3
  right: 15%
  bottom: 25%
  animation-delay: 4s

@keyframes float
  0%, 100%
    transform: translateY(0) rotate(0deg)
  50%
    transform: translateY(-20px) rotate(5deg)

// Social Proof Section
.social-proof-section
  background: $forest
  padding: 2.5rem 2rem

.stats-container
  align-items: center
  display: flex
  flex-wrap: wrap
  gap: 2rem
  justify-content: center
  margin: 0 auto
  max-width: 900px

.stat-item
  text-align: center

.stat-number
  color: $gold
  display: block
  font-family: 'Georgia', serif
  font-size: clamp(2rem, 4vw, 3rem)
  font-weight: 700

.stat-label
  color: rgba(255, 255, 255, 0.85)
  font-size: 0.95rem
  text-transform: uppercase
  letter-spacing: 1px

.stat-divider
  background: rgba(255, 255, 255, 0.2)
  height: 50px
  width: 1px
  @media screen and (max-width: 600px)
    display: none

// How it Works Section
.how-it-works-section
  background: $warm-white
  padding: 5rem 2rem

.section-header
  margin-bottom: 3rem
  text-align: center
  h2
    color: $charcoal
    font-family: 'Georgia', serif
    font-size: clamp(1.8rem, 4vw, 2.5rem)
    font-weight: 700
    margin-bottom: 0.75rem
  p
    color: $text-secondary
    font-size: 1.1rem
    max-width: 600px
    margin: 0 auto

  &.light
    h2
      color: white
    p
      color: rgba(255, 255, 255, 0.85)

.steps-container
  align-items: center
  display: flex
  flex-wrap: wrap
  gap: 1rem
  justify-content: center
  margin: 0 auto
  max-width: 1100px

.step-card
  background: white
  border-radius: 20px
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06)
  max-width: 280px
  padding: 2rem
  position: relative
  text-align: center
  transition: all 0.3s ease
  &:hover
    box-shadow: 0 8px 30px rgba($amber, 0.15)
    transform: translateY(-4px)

.step-number
  background: linear-gradient(135deg, $amber 0%, $amber-dark 100%)
  border-radius: 50%
  color: white
  font-size: 0.9rem
  font-weight: 700
  height: 28px
  left: 1rem
  line-height: 28px
  position: absolute
  top: 1rem
  width: 28px

.step-icon
  background: rgba($amber, 0.1)
  border-radius: 50%
  color: $amber-dark
  height: 80px
  line-height: 80px
  margin: 0 auto 1.25rem
  width: 80px
  display: flex
  align-items: center
  justify-content: center

.step-card h3
  color: $charcoal
  font-size: 1.15rem
  font-weight: 600
  margin-bottom: 0.5rem

.step-card p
  color: $text-secondary
  font-size: 0.95rem
  line-height: 1.5
  margin: 0

.step-arrow
  color: $amber
  @media screen and (max-width: 900px)
    display: none

// Features Section
.features-section
  background: $paper
  padding: 5rem 2rem

.features-container
  display: grid
  gap: 1.5rem
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))
  margin: 0 auto
  max-width: 1200px

.feature-card
  background: white
  border-radius: 16px
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04)
  padding: 2rem
  transition: all 0.3s ease
  &:hover
    box-shadow: 0 8px 30px rgba($amber, 0.12)
    transform: translateY(-4px)

.feature-icon
  background: linear-gradient(135deg, rgba($amber, 0.15) 0%, rgba($amber-dark, 0.1) 100%)
  border-radius: 12px
  color: $amber-dark
  height: 60px
  margin-bottom: 1.25rem
  width: 60px
  display: flex
  align-items: center
  justify-content: center

.feature-card h3
  color: $charcoal
  font-size: 1.15rem
  font-weight: 600
  margin-bottom: 0.5rem

.feature-card p
  color: $text-secondary
  font-size: 0.95rem
  line-height: 1.6
  margin: 0

// Testimonials Section
.testimonials-section
  background: linear-gradient(135deg, $burgundy 0%, $burgundy-light 100%)
  padding: 5rem 2rem

.testimonials-container
  display: grid
  gap: 1.5rem
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))
  margin: 0 auto
  max-width: 1100px

.testimonial-card
  background: rgba(255, 255, 255, 0.1)
  backdrop-filter: blur(10px)
  border: 1px solid rgba(255, 255, 255, 0.15)
  border-radius: 16px
  padding: 2rem

.testimonial-quote
  color: rgba(255, 255, 255, 0.3)
  margin-bottom: 0.5rem

.testimonial-text
  color: white
  font-size: 1rem
  font-style: italic
  line-height: 1.7
  margin-bottom: 1.5rem

.testimonial-author
  align-items: center
  display: flex
  gap: 1rem

.author-avatar
  align-items: center
  background: rgba(255, 255, 255, 0.2)
  border-radius: 50%
  color: white
  display: flex
  height: 48px
  justify-content: center
  width: 48px

.author-info
  display: flex
  flex-direction: column

.author-name
  color: white
  font-weight: 600

.author-books
  color: rgba(255, 255, 255, 0.7)
  font-size: 0.85rem

// CTA Section
.cta-section
  background: $charcoal
  padding: 5rem 2rem
  text-align: center

.cta-content
  margin: 0 auto
  max-width: 650px

  h2
    color: white
    font-family: 'Georgia', serif
    font-size: clamp(1.8rem, 4vw, 2.8rem)
    font-weight: 700
    margin-bottom: 1rem

  p
    color: rgba(255, 255, 255, 0.8)
    font-size: 1.15rem
    margin-bottom: 2.5rem

.cta-buttons
  display: flex
  flex-wrap: wrap
  gap: 1rem
  justify-content: center

.cta-primary-btn
  font-weight: 600
  transition: all 0.2s ease
  &:hover
    box-shadow: 0 6px 20px rgba($amber, 0.4)
    transform: translateY(-2px)

.cta-secondary-btn
  border-color: rgba(255, 255, 255, 0.3)
  transition: all 0.2s ease
  &:hover
    background: rgba(255, 255, 255, 0.1)
    border-color: rgba(255, 255, 255, 0.5)

// Footer
.landing-footer
  background: darken($charcoal, 5%)
  padding: 3rem 2rem 1.5rem

.footer-content
  align-items: center
  display: flex
  flex-wrap: wrap
  gap: 2rem
  justify-content: space-between
  margin: 0 auto 2rem
  max-width: 1100px

.footer-brand
  .footer-logo
    height: 40px
    margin-bottom: 0.5rem
  p
    color: rgba(255, 255, 255, 0.6)
    font-size: 0.9rem
    margin: 0

.footer-links
  display: flex
  gap: 2rem
  a
    color: rgba(255, 255, 255, 0.7)
    text-decoration: none
    transition: color 0.2s ease
    &:hover
      color: $amber

.footer-bottom
  border-top: 1px solid rgba(255, 255, 255, 0.1)
  padding-top: 1.5rem
  text-align: center
  p
    color: rgba(255, 255, 255, 0.5)
    font-size: 0.85rem
    margin: 0
</style>
