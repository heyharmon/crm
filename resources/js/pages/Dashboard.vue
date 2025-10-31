<script setup>
import { computed, onMounted, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import api from '@/services/api'
import { getRatingPillClasses } from '@/utils/ratingStyles'

const loading = ref(true)
const error = ref(null)
const stats = ref(null)

const integerFormatter = new Intl.NumberFormat()
const decimalFormatter = new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 1
})

const formatInteger = (value) => integerFormatter.format(value ?? 0)
const formatDecimal = (value) => decimalFormatter.format(value ?? 0)

const fetchStats = async () => {
    loading.value = true
    error.value = null
    try {
        stats.value = await api.get('/dashboard')
    } catch (err) {
        console.error('Failed to load dashboard metrics:', err)
        error.value = err?.message ?? 'Unable to load dashboard metrics.'
    } finally {
        loading.value = false
    }
}

onMounted(fetchStats)

const ratingDistribution = computed(() => stats.value?.ratings?.distribution ?? [])
const totals = computed(() => stats.value?.totals ?? {})
const ratings = computed(() => stats.value?.ratings ?? {})
const redesigns = computed(() => stats.value?.redesigns ?? {})
const redesignIntervals = [1, 2, 3, 4, 5]
const redesignCounts = computed(() => {
    const counts = stats.value?.redesigns?.counts_by_years ?? {}
    return redesignIntervals.map((years) => ({
        years,
        label: years === 1 ? 'Last year' : `Last ${years} years`,
        count: counts[`within_${years}_years`] ?? 0
    }))
})
const cms = computed(() => stats.value?.cms ?? {})
const cmsDistribution = computed(() => stats.value?.cms?.distribution ?? [])

const ratingBadgeClasses = (option) => getRatingPillClasses(option?.slug)
</script>

<template>
    <DefaultLayout>
        <section class="py-10">
            <header class="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide text-neutral-500">Overview</p>
                    <h1 class="text-3xl font-semibold text-neutral-900">Dashboard</h1>
                    <p class="mt-2 text-sm text-neutral-500">Snapshot of organization coverage, website ratings, and redesign activity.</p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading"
                    @click="fetchStats"
                >
                    {{ loading ? 'Refreshing…' : 'Refresh stats' }}
                </button>
            </header>

            <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <div v-else-if="loading" class="rounded-xl border border-neutral-200 bg-white px-4 py-3 text-sm text-neutral-600">Loading dashboard metrics…</div>

            <div v-else class="space-y-8">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Total organizations</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900">
                            {{ formatInteger(totals.organizations) }}
                        </p>
                        <p class="mt-1 text-sm text-neutral-500">Active records in the CRM</p>
                        <router-link
                            to="/organizations"
                            class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-neutral-600 underline underline-offset-4 transition hover:text-neutral-900"
                        >
                            View Organizations
                        </router-link>
                    </article>

                    <article class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Missing website ratings</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900">
                            {{ formatInteger(totals.organizations_without_ratings) }}
                        </p>
                        <p class="mt-1 text-sm text-neutral-500">Websites still waiting for a score</p>
                        <router-link
                            :to="{ name: 'websites.ratings' }"
                            class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-neutral-600 underline underline-offset-4 transition hover:text-neutral-900"
                        >
                            Rate These Websites
                        </router-link>
                    </article>

                    <article class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Websites you rated</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900">
                            {{ formatInteger(ratings.user_rated_websites) }}
                        </p>
                        <p class="mt-1 text-sm text-neutral-500">Your personal contributions</p>
                    </article>

                    <article class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Pages crawled</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900">
                            {{ formatInteger(totals.pages_tracked) }}
                        </p>
                        <p class="mt-1 text-sm text-neutral-500">Avg. {{ formatDecimal(totals.average_pages_per_organization) }} pages / org website</p>
                    </article>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm lg:col-span-2">
                        <header class="mb-4 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Website ratings</p>
                                <h2 class="text-xl font-semibold text-neutral-900">Rating distribution</h2>
                            </div>
                            <span class="text-sm font-medium text-neutral-500"> {{ formatInteger(ratings.total_ratings) }} total ratings </span>
                        </header>

                        <div v-if="ratingDistribution.length" class="space-y-4">
                            <div v-for="option in ratingDistribution" :key="option.id" class="space-y-2 rounded-xl border border-neutral-100 p-4">
                                <div class="flex items-baseline justify-between gap-4 text-sm">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
                                            :class="ratingBadgeClasses(option)"
                                        >
                                            {{ option.name }}
                                        </span>
                                        <span class="text-xs text-neutral-500">Score {{ option.score }}</span>
                                    </div>
                                    <div class="text-sm font-semibold text-neutral-700">
                                        {{ formatInteger(option.count) }}
                                        <span class="ml-1 text-xs font-normal text-neutral-500"> ({{ formatDecimal(option.percentage) }}%) </span>
                                    </div>
                                </div>
                                <div class="h-2 rounded-full bg-neutral-100">
                                    <div class="h-full rounded-full bg-neutral-900 transition-all" :style="{ width: `${option.percentage}%` }" />
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-neutral-500">No website ratings have been recorded yet.</p>
                    </section>

                    <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                        <header class="mb-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Website redesigns</p>
                            <h2 class="text-xl font-semibold text-neutral-900">Redesign activity</h2>
                        </header>

                        <div class="space-y-4">
                            <div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Median time since redesign</p>
                                <p class="mt-1 text-3xl font-semibold text-neutral-900">
                                    {{ redesigns.median_duration_human ?? '—' }}
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="bucket in redesignCounts"
                                    :key="bucket.years"
                                    class="flex items-baseline justify-between border-b border-neutral-100 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <div class="text-sm text-neutral-500">{{ bucket.label }}</div>
                                    <div class="text-base font-semibold text-neutral-900">{{ formatInteger(bucket.count) }}</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <header class="mb-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Content Management Systems</p>
                            <h2 class="text-xl font-semibold text-neutral-900">CMS distribution</h2>
                        </div>
                        <span class="text-sm font-medium text-neutral-500"> {{ formatInteger(cms.total_with_cms) }} organizations </span>
                    </header>

                    <div v-if="cmsDistribution.length" class="space-y-4">
                        <div v-for="item in cmsDistribution" :key="item.name" class="space-y-2">
                            <div class="flex items-baseline justify-between gap-4 text-sm">
                                <div class="font-medium text-neutral-700">{{ item.name }}</div>
                                <div class="text-sm font-semibold text-neutral-700">
                                    {{ formatInteger(item.count) }}
                                    <span class="ml-1 text-xs font-normal text-neutral-500"> ({{ formatDecimal(item.percentage) }}%) </span>
                                </div>
                            </div>
                            <div class="h-2 rounded-full bg-neutral-100">
                                <div class="h-full rounded-full bg-neutral-900 transition-all" :style="{ width: `${item.percentage}%` }" />
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-neutral-500">No CMS data has been recorded yet.</p>
                </section>
            </div>
        </section>
    </DefaultLayout>
</template>
