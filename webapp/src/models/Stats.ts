export interface ByStatusData {
  want_to_read: number
  reading: number
  read: number
  abandoned: number
  on_hold: number
  re_reading: number
}

export interface ByMonthData {
  year: number
  month: number
  count: number
}

export interface SubcategoryData {
  name: string
  count: number
}

export interface ByCategoryData {
  main_category: string
  total: number
  subcategories: SubcategoryData[]
}

export interface StatsResponse {
  by_status: ByStatusData
  by_month: ByMonthData[]
  by_category: ByCategoryData[]
}

export interface CategorySeriesItem {
  name: string
  type: 'bar'
  stack: string
  emphasis: { focus: string }
  barWidth: string
  itemStyle: { color: string; borderRadius?: number[] }
  label: { show: boolean }
  data: number[]
}
