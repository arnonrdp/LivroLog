<template>
  <q-btn dense flat icon="menu" @click="shelfMenu = true" />

  <q-dialog v-model="shelfMenu" position="right">
    <q-card>
      <q-card-section>
        <q-input
          dense
          debounce="300"
          outlined
          :placeholder="$t('book.search')"
          :model-value="modelValue"
          @update:model-value="$emit('update:model-value', $event)"
        >
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </q-card-section>
      <q-card-section>
        {{ $t('book.sort') }}:
        <q-list bordered class="non-selectable">
          <q-item clickable v-for="(label, value) in bookLabels" :key="label" @click="$emit('sort', value)">
            <q-item-section>{{ label }}</q-item-section>
            <q-item-section avatar>
              <q-icon v-if="value === sortKey" size="xs" :name="ascDesc === 'asc' ? 'arrow_downward' : 'arrow_upward'" />
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineEmits(['sort', 'update:model-value'])
defineProps<{
  modelValue: string
}>()

const { t } = useI18n()

const ascDesc = ref('asc')
const shelfMenu = ref(false)
const sortKey = ref<string | number>('')

const bookLabels = ref({
  authors: t('book.order-by-author'),
  addedIn: t('book.order-by-date'),
  readIn: t('book.order-by-read'),
  title: t('book.order-by-title')
})
</script>
