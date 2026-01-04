<template>
  <div v-if="isLoading" class="text-center q-py-md">
    <q-spinner color="primary" size="2em" />
  </div>

  <div v-else-if="hasData" class="reading-stats q-mb-lg">
    <div class="text-h6 q-mb-md">{{ $t('reading-stats') }}</div>

    <div class="row q-col-gutter-md items-stretch">
      <!-- Radar Chart: Reading Status Distribution -->
      <div class="col-12 col-md-6">
        <q-card bordered class="full-height" flat>
          <q-card-section class="full-height column">
            <div class="text-subtitle2 text-grey-7 q-mb-sm">{{ $t('reading-status') }}</div>
            <v-chart autoresize class="chart-radar col-grow" :option="statusChartOption" />
          </q-card-section>
        </q-card>
      </div>

      <!-- Bar Chart: Books Read per Month/Year -->
      <div class="col-12 col-md-6">
        <q-card bordered class="full-height" flat>
          <q-card-section class="full-height column">
            <div class="flex items-center q-mb-sm">
              <div class="text-subtitle2 text-grey-7">
                {{ timeGrouping === 'month' ? $t('books-per-month') : $t('books-per-year') }}
              </div>
              <q-space />
              <q-btn-toggle
                v-model="timeGrouping"
                dense
                no-caps
                :options="timeGroupingOptions"
                padding="2px 8px"
                rounded
                size="sm"
                toggle-color="primary"
                unelevated
              />
            </div>
            <v-chart autoresize class="chart col-grow" :option="timeChartOption" />
          </q-card-section>
        </q-card>
      </div>

      <!-- Horizontal Bar Chart: Top Categories -->
      <div class="col-12">
        <q-card bordered flat>
          <q-card-section>
            <div class="text-subtitle2 text-grey-7 q-mb-sm">{{ $t('top-categories') }}</div>
            <v-chart autoresize class="chart-categories" :option="categoryChartOption" />
          </q-card-section>
        </q-card>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// External libraries
import { use } from 'echarts/core'
import { BarChart, RadarChart } from 'echarts/charts'
import { GridComponent, LegendComponent, RadarComponent, TitleComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

// Internal imports
import api from '@/utils/axios'
import type { CategorySeriesItem, ReadingStatus, StatsResponse } from '@/models'

// Register ECharts components
use([CanvasRenderer, RadarChart, BarChart, GridComponent, RadarComponent, TitleComponent, TooltipComponent, LegendComponent])

// Constants
const STATUS_COLORS: Record<ReadingStatus, string> = {
  want_to_read: '#1976D2', // blue
  reading: '#FF9800', // orange
  read: '#4CAF50', // green
  abandoned: '#F44336', // red
  on_hold: '#9E9E9E', // grey
  re_reading: '#9C27B0' // purple
}

const SUBCATEGORY_COLORS = [
  '#5C6BC0', // indigo
  '#26A69A', // teal
  '#FF7043', // deep orange
  '#AB47BC', // purple
  '#42A5F5', // blue
  '#FFA726', // orange
  '#66BB6A', // green
  '#EC407A', // pink
  '#78909C', // blue grey
  '#8D6E63', // brown
  '#29B6F6', // light blue
  '#9CCC65', // light green
  '#FFCA28', // amber
  '#7E57C2', // deep purple
  '#26C6DA' // cyan
]

// "Lido" at top (12h), "Quero Ler" at bottom (6h), clockwise sequence from bottom
// ECharts renders counter-clockwise, so array order compensates for visual clockwise display
const STATUS_ORDER: ReadingStatus[] = ['read', 'reading', 'on_hold', 'want_to_read', 'abandoned', 're_reading']

// Props
const props = defineProps<{
  username: string
}>()

// Composables
const { t, locale } = useI18n()

// State
const isLoading = ref(false)
const stats = ref<StatsResponse | null>(null)
const timeGrouping = ref<'month' | 'year'>('year')

// Computed - Data
const hasData = computed(() => {
  if (!stats.value) return false
  const { by_status, by_month, by_category } = stats.value
  const totalBooks = Object.values(by_status).reduce((sum, count) => sum + count, 0)
  return totalBooks > 0 || by_month.length > 0 || by_category.length > 0
})

const monthNames = computed(() => [
  t('months.jan'),
  t('months.feb'),
  t('months.mar'),
  t('months.apr'),
  t('months.may'),
  t('months.jun'),
  t('months.jul'),
  t('months.aug'),
  t('months.sep'),
  t('months.oct'),
  t('months.nov'),
  t('months.dec')
])

const timeGroupingOptions = computed(() => [
  { value: 'month', label: t('month') },
  { value: 'year', label: t('year') }
])

// Computed - Chart Options
const statusChartOption = computed(() => {
  if (!stats.value) return {}

  const maxValue = Math.max(...Object.values(stats.value.by_status), 1)
  const values = STATUS_ORDER.map((status) => stats.value?.by_status[status] ?? 0)

  const indicators = STATUS_ORDER.map((status) => ({
    name: t(`reading-statuses.${status}`),
    max: maxValue,
    color: STATUS_COLORS[status]
  }))

  return {
    tooltip: {
      trigger: 'item',
      formatter: (params: { value: number[]; name: string }) => {
        let html = `<strong>${params.name}</strong><br/>`
        STATUS_ORDER.forEach((status, i) => {
          const count = params.value[i] ?? 0
          if (count > 0) {
            const color = STATUS_COLORS[status]
            const label = t(`reading-statuses.${status}`)
            html += `<span style="display:inline-block;width:10px;height:10px;background:${color};border-radius:2px;margin-right:5px;"></span>${label}: ${count}<br/>`
          }
        })
        return html
      }
    },
    radar: {
      indicator: indicators,
      shape: 'polygon',
      radius: '70%',
      center: ['50%', '50%'],
      startAngle: 90,
      clockwise: true,
      splitNumber: 4,
      axisName: {
        fontSize: 12,
        fontWeight: 500
      },
      splitArea: {
        areaStyle: {
          color: ['rgba(0,0,0,0.02)', 'rgba(0,0,0,0.04)']
        }
      },
      axisLine: {
        lineStyle: {
          color: 'rgba(0, 0, 0, 0.1)'
        }
      },
      splitLine: {
        lineStyle: {
          color: 'rgba(0, 0, 0, 0.1)'
        }
      }
    },
    series: [
      {
        type: 'radar',
        symbol: 'none',
        data: [
          {
            value: values,
            name: t('reading-stats'),
            areaStyle: {
              color: 'rgba(184, 149, 106, 0.25)'
            },
            lineStyle: {
              color: 'rgba(184, 149, 106, 0.6)',
              width: 2
            }
          }
        ]
      },
      // Individual points with status colors (only for values > 0)
      {
        type: 'radar',
        symbol: 'circle',
        symbolSize: 10,
        data: [
          {
            value: values.map((v) => (v > 0 ? v : null)),
            itemStyle: {
              color: (params: { dataIndex: number }) => {
                const status = STATUS_ORDER[params.dataIndex]
                return status ? STATUS_COLORS[status] : '#999'
              }
            },
            lineStyle: { width: 0 },
            areaStyle: { opacity: 0 }
          }
        ]
      }
    ]
  }
})

const timeChartOption = computed(() => {
  if (!stats.value || stats.value.by_month.length === 0) return {}

  // Create a map of existing data for quick lookup
  const dataMap = new Map<string, number>()
  stats.value.by_month.forEach((item) => {
    dataMap.set(`${item.year}-${item.month}`, item.count)
  })

  // Array is guaranteed non-empty by early return above
  const firstItem = stats.value.by_month[0]!
  const lastItem = stats.value.by_month[stats.value.by_month.length - 1]!

  let labels: string[]
  let values: number[]

  if (timeGrouping.value === 'month') {
    labels = []
    values = []

    let currentYear = firstItem.year
    let currentMonth = firstItem.month

    while (currentYear < lastItem.year || (currentYear === lastItem.year && currentMonth <= lastItem.month)) {
      labels.push(`${monthNames.value[currentMonth - 1]}/${currentYear.toString().slice(-2)}`)
      values.push(dataMap.get(`${currentYear}-${currentMonth}`) || 0)

      currentMonth++
      if (currentMonth > 12) {
        currentMonth = 1
        currentYear++
      }
    }
  } else {
    // Aggregate by year
    const yearData = new Map<number, number>()
    stats.value.by_month.forEach((item) => {
      yearData.set(item.year, (yearData.get(item.year) || 0) + item.count)
    })

    labels = []
    values = []

    for (let year = firstItem.year; year <= lastItem.year; year++) {
      labels.push(String(year))
      values.push(yearData.get(year) || 0)
    }
  }

  return {
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' }
    },
    grid: {
      left: '3%',
      right: '4%',
      bottom: '3%',
      containLabel: true
    },
    xAxis: {
      type: 'category',
      data: labels,
      axisLabel: {
        rotate: timeGrouping.value === 'month' ? 45 : 0,
        fontSize: timeGrouping.value === 'month' ? 10 : 12
      }
    },
    yAxis: {
      type: 'value',
      minInterval: 1
    },
    series: [
      {
        type: 'bar',
        data: values,
        itemStyle: {
          color: '#1976D2'
        }
      }
    ]
  }
})

const categoryChartOption = computed(() => {
  if (!stats.value || stats.value.by_category.length === 0) return {}

  const categories = stats.value.by_category
  const mainCategories = categories.map((c) => c.main_category)

  // Collect all unique subcategories
  const subcategoryList = [...new Set(categories.flatMap((cat) => cat.subcategories.map((sub) => sub.name)))]

  // Create a series for each subcategory
  const series: CategorySeriesItem[] = subcategoryList.map((subName, index) => ({
    name: subName,
    type: 'bar',
    stack: 'total',
    emphasis: { focus: 'series' },
    barWidth: '60%',
    itemStyle: {
      color: SUBCATEGORY_COLORS[index % SUBCATEGORY_COLORS.length] || '#4CAF50',
      borderRadius: [0, 0, 0, 0]
    },
    label: { show: false },
    data: categories.map((cat) => cat.subcategories.find((s) => s.name === subName)?.count ?? 0)
  }))

  return {
    tooltip: {
      trigger: 'item',
      formatter: (params: { seriesName: string; value: number; dataIndex: number }) => {
        if (!params || params.value === 0) return ''

        const catData = categories[params.dataIndex]
        if (!catData) return ''

        const total = catData.total
        const bookLabel = total === 1 ? t('books', 1) : t('books', 2)
        let html = `<strong>${catData.main_category}</strong> (${total} ${bookLabel})<br/>`

        catData.subcategories.forEach((sub) => {
          const colorIndex = subcategoryList.indexOf(sub.name) % SUBCATEGORY_COLORS.length
          const color = SUBCATEGORY_COLORS[colorIndex] || '#999'
          const isHovered = sub.name === params.seriesName
          const style = isHovered ? 'font-weight:bold;' : ''
          html += `<span style="display:inline-block;width:10px;height:10px;background:${color};border-radius:2px;margin-right:5px;"></span><span style="${style}">${sub.name}: ${sub.count}</span><br/>`
        })

        return html
      }
    },
    grid: {
      left: '3%',
      right: '4%',
      bottom: '3%',
      top: '3%',
      containLabel: true
    },
    xAxis: {
      type: 'value',
      show: false
    },
    yAxis: {
      type: 'category',
      data: mainCategories,
      inverse: true,
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: {
        show: true,
        fontSize: 12,
        color: '#555',
        fontWeight: 500
      }
    },
    series
  }
})

// Lifecycle
onMounted(() => {
  fetchStats()
})

// Watchers
watch(
  () => props.username,
  () => fetchStats(),
  { immediate: false }
)

watch(locale, () => {
  // Force re-render when locale changes by toggling stats
  if (stats.value) {
    const currentStats = stats.value
    stats.value = null
    window.setTimeout(() => {
      stats.value = currentStats
    }, 0)
  }
})

// Functions
function fetchStats() {
  if (!props.username) return

  isLoading.value = true
  api
    .get<StatsResponse>(`/users/${props.username}/stats`)
    .then((response) => {
      stats.value = response.data
    })
    .catch(() => {
      stats.value = null
    })
    .finally(() => {
      isLoading.value = false
    })
}
</script>

<style scoped>
.full-height {
  height: 100%;
}

.chart {
  min-height: 200px;
  width: 100%;
}

.chart-radar {
  min-height: 280px;
  width: 100%;
}

.chart-categories {
  height: 240px;
  width: 100%;
}
</style>
