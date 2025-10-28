<script setup>
import { computed } from 'vue'
import { formatDisplayDate } from '@/utils/date'

const props = defineProps({
    event: { type: Object, default: null },
    open: { type: Boolean, default: false },
    formatShellChange: { type: Function, required: true },
    htmlClassCountLabel: { type: Function, required: true },
    bodyClassCountLabel: { type: Function, required: true },
    getHtmlClasses: { type: Function, required: true },
    getBodyClasses: { type: Function, required: true },
    redesignViewCapturedAt: { type: Function, required: true },
    getRedesignScreenshotUrl: { type: Function, required: true },
    isRedesignPreviewLoading: { type: Function, required: true },
    hasRedesignPreviewError: { type: Function, required: true },
    buildRedesignArchivedUrl: { type: Function, required: true }
})

const emit = defineEmits(['close'])

const isOpen = computed(() => props.open && !!props.event)

const handleClose = () => {
    emit('close')
}

const shellChangeLabel = computed(() => {
    if (!props.event) return null
    return props.formatShellChange(props.event)
})

const beforeHtmlClasses = computed(() => (props.event ? props.getHtmlClasses(props.event, 'before') : []))
const afterHtmlClasses = computed(() => (props.event ? props.getHtmlClasses(props.event, 'after') : []))

const beforeBodyClasses = computed(() => (props.event ? props.getBodyClasses(props.event, 'before') : []))
const afterBodyClasses = computed(() => (props.event ? props.getBodyClasses(props.event, 'after') : []))


const buildPreviewList = (values, maxItems = 6) => {
    if (!Array.isArray(values) || values.length === 0) {
        return []
    }

    const unique = [...new Set(values.map((value) => (typeof value === 'string' ? value.trim() : '')).filter(Boolean))]

    if (unique.length <= maxItems) {
        return unique
    }

    return [...unique.slice(0, maxItems), '…']
}

const beforeHtmlClassesPreview = computed(() => buildPreviewList(beforeHtmlClasses.value))
const afterHtmlClassesPreview = computed(() => buildPreviewList(afterHtmlClasses.value))

const beforeBodyClassesPreview = computed(() => buildPreviewList(beforeBodyClasses.value))
const afterBodyClassesPreview = computed(() => buildPreviewList(afterBodyClasses.value))


const beforeCapturedAt = computed(() => (props.event ? props.redesignViewCapturedAt(props.event, 'before') : null))
const afterCapturedAt = computed(() => (props.event ? props.redesignViewCapturedAt(props.event, 'after') : null))

const beforeScreenshotUrl = computed(() => (props.event ? props.getRedesignScreenshotUrl(props.event, 'before') : null))
const afterScreenshotUrl = computed(() => (props.event ? props.getRedesignScreenshotUrl(props.event, 'after') : null))

const beforeArchiveUrl = computed(() => (props.event ? props.buildRedesignArchivedUrl(props.event, 'before') : null))
const afterArchiveUrl = computed(() => (props.event ? props.buildRedesignArchivedUrl(props.event, 'after') : null))

const beforeHtmlClassCount = computed(() => (props.event ? props.htmlClassCountLabel(props.event, 'before') : null))
const afterHtmlClassCount = computed(() => (props.event ? props.htmlClassCountLabel(props.event, 'after') : null))

const beforeBodyClassCount = computed(() => (props.event ? props.bodyClassCountLabel(props.event, 'before') : null))
const afterBodyClassCount = computed(() => (props.event ? props.bodyClassCountLabel(props.event, 'after') : null))


const isBeforeLoading = computed(() => props.event && props.isRedesignPreviewLoading(props.event, 'before'))
const isAfterLoading = computed(() => props.event && props.isRedesignPreviewLoading(props.event, 'after'))

const isBeforeError = computed(() => props.event && props.hasRedesignPreviewError(props.event, 'before'))
const isAfterError = computed(() => props.event && props.hasRedesignPreviewError(props.event, 'after'))
</script>

<template>
    <teleport to="body">
        <transition name="fade">
            <div v-if="isOpen" class="fixed inset-0 z-40 flex">
                <div class="absolute inset-0 bg-black/40" @click="handleClose" />
                <div class="relative ml-auto h-full w-full max-w-5xl bg-white shadow-2xl">
                    <div class="flex h-full flex-col">
                        <div class="flex items-start justify-between border-b border-neutral-200 px-6 py-4">
                            <div>
                                <h3 class="text-lg font-semibold text-neutral-900">Redesign details</h3>
                                <p class="text-xs text-neutral-500">
                                    {{ formatDisplayDate(beforeCapturedAt) || 'Unknown' }} →
                                    {{ formatDisplayDate(afterCapturedAt) || 'Unknown' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="shellChangeLabel" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                    {{ shellChangeLabel }}
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-full border border-neutral-300 px-3 py-1 text-xs font-semibold text-neutral-700 transition hover:border-neutral-400 hover:bg-neutral-200/60"
                                    @click="handleClose"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto bg-neutral-50">
                            <div class="grid gap-6 p-6 md:grid-cols-2">
                                <section class="space-y-4">
                                    <header class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                        <span>Before</span>
                                        <span v-if="beforeCapturedAt" class="text-[11px] font-medium text-neutral-500">
                                            {{ formatDisplayDate(beforeCapturedAt) }}
                                        </span>
                                    </header>
                                    <div class="relative h-64 overflow-hidden rounded-lg border border-neutral-200 bg-white">
                                        <img
                                            v-if="beforeScreenshotUrl"
                                            :src="beforeScreenshotUrl"
                                            :alt="`Archived screenshot before redesign (${formatDisplayDate(beforeCapturedAt) || 'Wayback'})`"
                                            class="absolute inset-0 h-full w-full object-cover transition-opacity duration-200"
                                            :class="isBeforeLoading ? 'opacity-0' : 'opacity-100'"
                                            loading="lazy"
                                        />
                                        <div v-if="isBeforeLoading" class="absolute inset-0 flex items-center justify-center gap-2 bg-white/75 text-xs font-medium text-neutral-600">
                                            <svg class="h-5 w-5 animate-spin text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z" />
                                            </svg>
                                            Loading screenshot…
                                        </div>
                                        <div v-else-if="isBeforeError || !beforeScreenshotUrl" class="absolute inset-0 flex items-center justify-center text-xs font-medium text-neutral-500">
                                            Preview unavailable
                                        </div>
                                    </div>
                                    <a
                                        v-if="beforeArchiveUrl"
                                        :href="beforeArchiveUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        View before snapshot →
                                    </a>
                                    <div class="flex min-h-[150px] flex-col gap-2 rounded-lg border border-neutral-200 bg-white p-3">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                            HTML classes
                                            <span v-if="beforeHtmlClassCount" class="ml-2 text-neutral-500">{{ beforeHtmlClassCount }}</span>
                                        </div>
                                        <ul v-if="beforeHtmlClassesPreview.length" class="space-y-1 text-xs text-neutral-700">
                                            <li v-for="(item, index) in beforeHtmlClassesPreview" :key="`before-html-${index}-${item}`">{{ item }}</li>
                                        </ul>
                                        <p v-else class="text-xs text-neutral-400">No classes captured.</p>
                                    </div>
                                    <div class="flex min-h-[150px] flex-col gap-2 rounded-lg border border-neutral-200 bg-white p-3">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                            Body classes
                                            <span v-if="beforeBodyClassCount" class="ml-2 text-neutral-500">{{ beforeBodyClassCount }}</span>
                                        </div>
                                        <ul v-if="beforeBodyClassesPreview.length" class="space-y-1 text-xs text-neutral-700">
                                            <li v-for="(item, index) in beforeBodyClassesPreview" :key="`before-body-${index}-${item}`">{{ item }}</li>
                                        </ul>
                                        <p v-else class="text-xs text-neutral-400">No classes captured.</p>
                                    </div>
                                </section>
                                <section class="space-y-4">
                                    <header class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                        <span>After</span>
                                        <span v-if="afterCapturedAt" class="text-[11px] font-medium text-neutral-500">
                                            {{ formatDisplayDate(afterCapturedAt) }}
                                        </span>
                                    </header>
                                    <div class="relative h-64 overflow-hidden rounded-lg border border-neutral-200 bg-white">
                                        <img
                                            v-if="afterScreenshotUrl"
                                            :src="afterScreenshotUrl"
                                            :alt="`Archived screenshot after redesign (${formatDisplayDate(afterCapturedAt) || 'Wayback'})`"
                                            class="absolute inset-0 h-full w-full object-cover transition-opacity duration-200"
                                            :class="isAfterLoading ? 'opacity-0' : 'opacity-100'"
                                            loading="lazy"
                                        />
                                        <div v-if="isAfterLoading" class="absolute inset-0 flex items-center justify-center gap-2 bg-white/75 text-xs font-medium text-neutral-600">
                                            <svg class="h-5 w-5 animate-spin text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z" />
                                            </svg>
                                            Loading screenshot…
                                        </div>
                                        <div v-else-if="isAfterError || !afterScreenshotUrl" class="absolute inset-0 flex items-center justify-center text-xs font-medium text-neutral-500">
                                            Preview unavailable
                                        </div>
                                    </div>
                                    <a
                                        v-if="afterArchiveUrl"
                                        :href="afterArchiveUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        View after snapshot →
                                    </a>
                                    <div class="flex min-h-[150px] flex-col gap-2 rounded-lg border border-neutral-200 bg-white p-3">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                            HTML classes
                                            <span v-if="afterHtmlClassCount" class="ml-2 text-neutral-500">{{ afterHtmlClassCount }}</span>
                                        </div>
                                        <ul v-if="afterHtmlClassesPreview.length" class="space-y-1 text-xs text-neutral-700">
                                            <li v-for="(item, index) in afterHtmlClassesPreview" :key="`after-html-${index}-${item}`">{{ item }}</li>
                                        </ul>
                                        <p v-else class="text-xs text-neutral-400">No classes captured.</p>
                                    </div>
                                    <div class="flex min-h-[150px] flex-col gap-2 rounded-lg border border-neutral-200 bg-white p-3">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                            Body classes
                                            <span v-if="afterBodyClassCount" class="ml-2 text-neutral-500">{{ afterBodyClassCount }}</span>
                                        </div>
                                        <ul v-if="afterBodyClassesPreview.length" class="space-y-1 text-xs text-neutral-700">
                                            <li v-for="(item, index) in afterBodyClassesPreview" :key="`after-body-${index}-${item}`">{{ item }}</li>
                                        </ul>
                                        <p v-else class="text-xs text-neutral-400">No classes captured.</p>
                                    </div>
                                </section>
                            </div>
                            <p class="px-6 pb-6 text-xs text-neutral-400">Data from the Internet Archive Wayback Machine</p>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
