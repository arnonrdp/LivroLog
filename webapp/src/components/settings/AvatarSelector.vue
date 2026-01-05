<template>
  <div class="avatar-selector">
    <!-- Current Avatar Preview -->
    <div class="current-avatar-section q-mb-lg">
      <div class="text-subtitle2 q-mb-sm">{{ $t('avatar.current') }}</div>
      <q-avatar class="current-avatar" size="100px">
        <q-img v-if="previewUrl" :src="previewUrl" @error="onImageError" />
        <q-icon v-else color="grey-6" name="person" size="60px" />
      </q-avatar>
      <div v-if="imageError" class="text-negative text-caption q-mt-sm">{{ $t('avatar.invalid-image') }}</div>
    </div>

    <!-- Toggle for Custom URL -->
    <div class="q-mb-md">
      <q-checkbox v-model="useCustomUrl" :label="$t('avatar.custom-url')" />
    </div>

    <!-- Custom URL Input (shown when toggle is on) -->
    <div v-if="useCustomUrl" class="q-mb-md custom-url-container">
      <q-input v-model="customUrl" :hint="$t('avatar.url-hint')" :label="$t('avatar.paste-url')" outlined @keyup.enter="applyCustomUrl">
        <template v-slot:append>
          <q-btn color="primary" :disable="!isValidUrl(customUrl)" flat icon="check" round @click="applyCustomUrl" />
        </template>
      </q-input>
    </div>

    <!-- Pre-defined Avatars Grid (hidden when using custom URL) -->
    <div v-else>
      <div class="text-subtitle2 q-mb-md text-center">{{ $t('avatar.choose') }}</div>
      <div class="avatars-grid q-mb-md">
        <div
          v-for="avatar in predefinedAvatars"
          :key="avatar.id"
          class="avatar-option"
          :class="{ 'avatar-selected': modelValue === avatar.url }"
          @click="selectAvatar(avatar.url)"
        >
          <q-avatar size="72px">
            <q-img :alt="avatar.label" :src="avatar.url" />
          </q-avatar>
        </div>
        <!-- Clear/Remove Avatar Option -->
        <div class="avatar-option" :class="{ 'avatar-selected': !modelValue }" @click="selectAvatar(null)">
          <q-avatar class="bg-grey-3" size="72px">
            <q-icon color="grey-6" name="person" size="40px" />
          </q-avatar>
          <q-tooltip>{{ $t('avatar.default') }}</q-tooltip>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

interface Props {
  modelValue: string | null | undefined
}

interface Emits {
  (e: 'update:modelValue', value: string | null): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Check if the URL is a predefined avatar (local path)
function isPredefinedAvatar(url: string | null | undefined): boolean {
  if (!url) return false
  return url.startsWith('/avatars/') && /^\/avatars\/avatar-\d+\.svg$/.test(url)
}

// Initialize state based on current modelValue
// If it's a custom URL (not predefined), show the custom URL input
const isInitialCustomUrl = props.modelValue && !isPredefinedAvatar(props.modelValue)
const customUrl = ref(isInitialCustomUrl ? props.modelValue : '')
const useCustomUrl = ref(!!isInitialCustomUrl)
const imageError = ref(false)

// Preview URL: shows custom URL if valid, otherwise shows current avatar
const previewUrl = computed(() => {
  if (useCustomUrl.value && customUrl.value && isValidUrl(customUrl.value)) {
    return customUrl.value.trim()
  }
  return props.modelValue || null
})

// When custom URL changes and is valid, automatically apply it
watch(customUrl, (newUrl) => {
  imageError.value = false
  if (useCustomUrl.value && newUrl && isValidUrl(newUrl)) {
    emit('update:modelValue', newUrl.trim())
  }
})

// When toggling custom URL mode
watch(useCustomUrl, (isCustom) => {
  if (isCustom && customUrl.value && isValidUrl(customUrl.value)) {
    // If toggling ON with a valid URL already entered, apply it
    emit('update:modelValue', customUrl.value.trim())
  } else if (!isCustom && customUrl.value) {
    // If toggling OFF, clear the custom URL input
    customUrl.value = ''
  }
})

function onImageError() {
  imageError.value = true
}

const predefinedAvatars = [
  { id: 1, url: '/avatars/avatar-1.svg', label: 'Avatar 1' },
  { id: 2, url: '/avatars/avatar-2.svg', label: 'Avatar 2' },
  { id: 3, url: '/avatars/avatar-3.svg', label: 'Avatar 3' },
  { id: 4, url: '/avatars/avatar-4.svg', label: 'Avatar 4' },
  { id: 5, url: '/avatars/avatar-5.svg', label: 'Avatar 5' },
  { id: 6, url: '/avatars/avatar-6.svg', label: 'Avatar 6' },
  { id: 7, url: '/avatars/avatar-7.svg', label: 'Avatar 7' },
  { id: 8, url: '/avatars/avatar-8.svg', label: 'Avatar 8' }
]

function selectAvatar(url: string | null) {
  emit('update:modelValue', url)
}

function isValidUrl(url: string): boolean {
  if (!url || url.trim().length === 0) return false

  try {
    const parsed = new URL(url.trim())

    // SECURITY: Only allow HTTPS protocol
    if (parsed.protocol !== 'https:') {
      return false
    }

    // SECURITY: Block dangerous patterns
    const dangerous = ['javascript:', 'data:', 'vbscript:', 'file:', 'about:', 'blob:']
    if (dangerous.some((d) => url.toLowerCase().includes(d))) {
      return false
    }

    // SECURITY: Block URLs with suspicious characters that could be used for injection
    if (/[<>"'`]/.test(url)) {
      return false
    }

    // SECURITY: Ensure hostname exists and is not localhost/internal
    const blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]']
    if (!parsed.hostname || blockedHosts.includes(parsed.hostname.toLowerCase())) {
      return false
    }

    // SECURITY: Block private IP ranges
    const ipPattern = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/
    const ipMatch = parsed.hostname.match(ipPattern)
    if (ipMatch && ipMatch[1] && ipMatch[2]) {
      const a = Number(ipMatch[1])
      const b = Number(ipMatch[2])
      // Block 10.x.x.x, 172.16-31.x.x, 192.168.x.x
      if (a === 10 || (a === 172 && b >= 16 && b <= 31) || (a === 192 && b === 168)) {
        return false
      }
    }

    return true
  } catch {
    return false
  }
}

function applyCustomUrl() {
  if (isValidUrl(customUrl.value)) {
    emit('update:modelValue', customUrl.value)
    customUrl.value = ''
  }
}
</script>

<style scoped lang="sass">
.avatar-selector
  width: 100%

.current-avatar-section
  display: flex
  flex-direction: column
  align-items: center

.current-avatar
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15)

.custom-url-container
  max-width: 400px
  margin: 0 auto

.avatars-grid
  display: grid
  grid-template-columns: repeat(3, 1fr)
  gap: 16px
  justify-items: center
  max-width: 300px
  margin: 0 auto

.avatar-option
  cursor: pointer
  padding: 4px
  border-radius: 50%
  border: 3px solid transparent
  transition: all 0.2s ease
  &:hover
    border-color: var(--q-primary)
    transform: scale(1.05)
  &.avatar-selected
    border-color: var(--q-primary)
    background-color: rgba(var(--q-primary-rgb), 0.1)
</style>
