<template>
  <q-dialog v-model="isOpen">
    <q-card style="min-width: 500px">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">{{ $t('import-goodreads-title') }}</div>
        <q-space />
        <q-btn v-close-popup dense flat icon="close" round />
      </q-card-section>

      <q-card-section>
        <!-- Instructions -->
        <div v-if="!importCompleted" class="q-mb-md">
          <p class="text-body2 q-mb-sm">{{ $t('import-goodreads-instructions') }}</p>

          <!-- Step-by-step with visual guide -->
          <div class="instruction-steps q-mb-md">
            <div class="step-item">
              <div class="step-number">1</div>
              <div class="step-content">
                <div class="text-body2 text-weight-medium">{{ $t('import-step-1-title') }}</div>
                <div class="text-caption text-grey-7">{{ $t('import-step-1-desc') }}</div>
                <q-btn
                  class="q-mt-sm"
                  color="primary"
                  dense
                  flat
                  icon-right="open_in_new"
                  label="goodreads.com/review/import"
                  size="sm"
                  @click="openGoodReadsExport"
                />
              </div>
            </div>

            <div class="step-item">
              <div class="step-number">2</div>
              <div class="step-content">
                <div class="text-body2 text-weight-medium">{{ $t('import-step-2-title') }}</div>
                <div class="text-caption text-grey-7">{{ $t('import-step-2-desc') }}</div>
              </div>
            </div>

            <div class="step-item">
              <div class="step-number">3</div>
              <div class="step-content">
                <div class="text-body2 text-weight-medium">{{ $t('import-step-3-title') }}</div>
                <div class="text-caption text-grey-7">{{ $t('import-step-3-desc') }}</div>
              </div>
            </div>
          </div>

          <q-banner class="bg-info text-white" dense rounded>
            <template #avatar>
              <q-icon name="info" />
            </template>
            {{ $t('import-note') }}
          </q-banner>
        </div>

        <!-- File Upload -->
        <div v-if="!importCompleted">
          <q-file
            v-model="selectedFile"
            accept=".csv"
            clearable
            filled
            :hint="$t('max-file-size')"
            :label="$t('select-csv-file')"
            max-file-size="10485760"
            @rejected="onFileRejected"
          >
            <template #prepend>
              <q-icon name="attach_file" />
            </template>
          </q-file>

          <q-btn
            class="q-mt-md full-width"
            color="primary"
            :disable="!selectedFile || isUploading"
            :label="$t('start-import')"
            :loading="isUploading"
            unelevated
            @click="startImport"
          />
        </div>

        <!-- Import Results -->
        <div v-else>
          <div class="text-center q-pa-md">
            <q-icon color="positive" name="check_circle" size="60px" />

            <div class="text-h6 q-mt-md">{{ $t('import-completed') }}</div>

            <!-- Statistics -->
            <div class="q-mt-lg">
              <q-list bordered class="rounded-borders">
                <q-item>
                  <q-item-section avatar>
                    <q-icon color="positive" name="check_circle" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ $t('imported-books') }}</q-item-label>
                    <q-item-label caption>{{ importedBooks }} {{ $t('books') }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item v-if="skippedBooks > 0">
                  <q-item-section avatar>
                    <q-icon color="warning" name="info" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ $t('skipped-books') }}</q-item-label>
                    <q-item-label caption>{{ skippedBooks }} {{ $t('already-in-library') }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item v-if="failedBooks > 0">
                  <q-item-section avatar>
                    <q-icon color="negative" name="error" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ $t('failed-books') }}</q-item-label>
                    <q-item-label caption>{{ failedBooks }} {{ $t('could-not-import') }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </div>

            <q-btn class="q-mt-md" color="primary" :label="$t('close')" unelevated @click="closeAndRefresh" />
          </div>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import api from '@/utils/axios'
import { useQuasar } from 'quasar'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  modelValue: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const $q = useQuasar()
const { t } = useI18n()

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const importCompleted = ref(false)
const totalBooks = ref(0)
const importedBooks = ref(0)
const skippedBooks = ref(0)
const failedBooks = ref(0)

watch(isOpen, (newValue) => {
  if (!newValue) {
    resetDialog()
  }
})

function onFileRejected() {
  $q.notify({
    type: 'negative',
    message: t('file-too-large'),
    position: 'top'
  })
}

function startImport() {
  if (!selectedFile.value) return

  isUploading.value = true

  const formData = new FormData()
  formData.append('file', selectedFile.value)

  api
    .post('/user/goodreads-imports', formData)
    .then((response) => {
      const stats = response.data.stats

      importCompleted.value = true
      totalBooks.value = stats.total
      importedBooks.value = stats.imported
      skippedBooks.value = stats.skipped
      failedBooks.value = stats.failed

      $q.notify({
        type: 'positive',
        message: t('import-completed'),
        position: 'top'
      })
    })
    .catch((error) => {
      console.error('Import error:', error)

      const errorMessage = error.response?.data?.error || error.response?.data?.message || t('import-error')

      $q.notify({
        type: 'negative',
        message: errorMessage,
        position: 'top'
      })
    })
    .finally(() => {
      isUploading.value = false
    })
}

function closeAndRefresh() {
  isOpen.value = false
  // Reload the page to show newly imported books
  window.location.reload()
}

function resetDialog() {
  selectedFile.value = null
  isUploading.value = false
  importCompleted.value = false
  totalBooks.value = 0
  importedBooks.value = 0
  skippedBooks.value = 0
  failedBooks.value = 0
}

function openGoodReadsExport() {
  window.open('https://www.goodreads.com/review/import', '_blank')
}
</script>

<style scoped lang="sass">
.instruction-steps
  .step-item
    display: flex
    gap: 1rem
    margin-bottom: 1.5rem

    &:last-child
      margin-bottom: 0

  .step-number
    flex-shrink: 0
    width: 32px
    height: 32px
    background: $primary
    color: white
    border-radius: 50%
    display: flex
    align-items: center
    justify-content: center
    font-weight: bold
    font-size: 1rem

  .step-content
    flex: 1
    padding-top: 0.25rem
</style>
