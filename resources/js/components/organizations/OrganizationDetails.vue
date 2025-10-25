<script setup>
import { onMounted, ref, watch } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'

const props = defineProps({
  organizationId: { type: [String, Number], required: true }
})

const organizationStore = useOrganizationStore()
const isLoadingLocal = ref(false)
const error = ref(null)
const screenshotStatus = ref('idle') // idle | loading | ready | error | empty
const screenshotError = ref(null)
const screenshotSrc = ref(null)
const activeScreenshotKey = ref(null)

const load = async () => {
  try {
    isLoadingLocal.value = true
    error.value = null
    await organizationStore.fetchOrganization(props.organizationId)
  } catch (e) {
    error.value = e?.message || 'Failed to load organization'
  } finally {
    isLoadingLocal.value = false
  }
}

onMounted(load)
const org = () => organizationStore.currentOrganization

const formatRatingLabel = (slug) => {
  if (!slug) return null
  return slug
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

const normalizeWebsite = (url) => {
  if (!url) return ''
  return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

function getScreenshotUrl(website) {
  if (!website) return null
  const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
  const accessKey = '3725d3868ee3426e82b2a3b9eebde219'
  const params = new URLSearchParams({
    access_key: accessKey,
    wait_until: 'network_idle',
    no_cookie_banners: 'true',
    url: normalizeWebsite(website)
  })
  return `${baseUrl}?${params.toString()}`
}

function resetScreenshot(status = 'idle') {
  screenshotStatus.value = status
  screenshotSrc.value = null
  screenshotError.value = null
  activeScreenshotKey.value = null
}

function loadScreenshot(organization) {
  if (!organization) {
    resetScreenshot('idle')
    return
  }

  if (!organization.website) {
    resetScreenshot('empty')
    return
  }

  const screenshotUrl = getScreenshotUrl(organization.website)
  if (!screenshotUrl) {
    screenshotError.value = 'Screenshot unavailable.'
    screenshotStatus.value = 'error'
    screenshotSrc.value = null
    return
  }

  const key = screenshotUrl
  activeScreenshotKey.value = key
  screenshotStatus.value = 'loading'
  screenshotError.value = null
  screenshotSrc.value = null

  const img = new Image()
  img.onload = () => {
    if (activeScreenshotKey.value !== key) return
    screenshotSrc.value = screenshotUrl
    screenshotStatus.value = 'ready'
  }
  img.onerror = () => {
    if (activeScreenshotKey.value !== key) return
    screenshotStatus.value = 'error'
    screenshotError.value = 'Screenshot unavailable.'
    screenshotSrc.value = null
  }
  img.src = screenshotUrl
}

watch(() => props.organizationId, load)
watch(
  () => organizationStore.currentOrganization,
  (organization) => {
    loadScreenshot(organization)
  },
  { immediate: true }
)
</script>

<template>
  <div class="h-full flex flex-col">
    <div v-if="isLoadingLocal" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
    </div>

    <div v-else-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded m-4">
      {{ error }}
    </div>

    <div v-else-if="org()" class="space-y-6 p-4">
      <div class="space-y-2">
        <h2 class="text-xl font-bold text-neutral-900">{{ org().name }}</h2>
        <p v-if="org().category" class="text-neutral-600">{{ org().category.name }}</p>
      </div>

      <div v-if="org().website" class="rounded-lg overflow-hidden border border-neutral-200">
        <img v-if="screenshotStatus === 'ready' && screenshotSrc" :src="screenshotSrc" :alt="org().name" class="w-full max-h-56 object-cover" />
        <div
          v-else
          class="flex h-56 w-full items-center justify-center bg-neutral-50 text-sm font-medium text-neutral-500"
        >
          <span v-if="screenshotStatus === 'loading'">Loading website preview…</span>
          <span v-else-if="screenshotStatus === 'error'">{{ screenshotError || 'Screenshot unavailable.' }}</span>
          <span v-else>Website preview unavailable.</span>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-neutral-200 p-4">
        <h3 class="font-semibold mb-3">Contact</h3>
        <div class="grid grid-cols-1 gap-2 text-sm">
          <div v-if="org().phone">
            <span class="font-medium text-neutral-700">Phone:</span>
            <a :href="`tel:${org().phone}`" class="ml-2 text-blue-600 hover:text-blue-800">{{ org().phone }}</a>
          </div>
          <div v-if="org().website">
            <span class="font-medium text-neutral-700">Website:</span>
            <a :href="org().formatted_website || org().website" target="_blank" rel="noopener noreferrer" class="ml-2 text-blue-600 hover:text-blue-800">{{ org().website }}</a>
          </div>
        </div>
      </div>

      <div v-if="org().full_address" class="bg-white rounded-lg border border-neutral-200 p-4">
        <h3 class="font-semibold mb-3">Address</h3>
        <p class="text-sm">{{ org().full_address }}</p>
        <div v-if="org().map_url" class="mt-2">
          <a :href="org().map_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 text-sm">View on Google Maps →</a>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-neutral-200 p-4">
        <h3 class="font-semibold mb-3">Website Ratings</h3>
        <div class="space-y-2 text-sm text-neutral-700">
          <div>
            <span class="font-medium text-neutral-900">Average:</span>
            <template v-if="org().website_rating_summary">
              {{ formatRatingLabel(org().website_rating_summary) }}
              <span v-if="org().website_rating_average !== null" class="text-neutral-500">
                ({{ Number(org().website_rating_average).toFixed(2) }})
              </span>
              <span v-if="org().website_rating_count" class="text-neutral-500">
                • {{ org().website_rating_count }} ratings
              </span>
            </template>
            <span v-else class="text-neutral-400">No ratings yet</span>
          </div>
          <div>
            <span class="font-medium text-neutral-900">Weighted:</span>
            <span v-if="org().website_rating_weighted !== null">
              {{ Number(org().website_rating_weighted).toFixed(2) }}
            </span>
            <span v-else class="text-neutral-400">No data</span>
          </div>
          <div>
            <span class="font-medium text-neutral-900">Your rating:</span>
            <span v-if="org().my_website_rating_option_name">
              {{ org().my_website_rating_option_name }}
            </span>
            <span v-else class="text-neutral-400">Not set</span>
          </div>
        </div>
      </div>

      <div v-if="org().notes" class="bg-white rounded-lg border border-neutral-200 p-4">
        <h3 class="font-semibold mb-3">Notes</h3>
        <p class="text-sm whitespace-pre-wrap">{{ org().notes }}</p>
      </div>
    </div>
  </div>
  
</template>
