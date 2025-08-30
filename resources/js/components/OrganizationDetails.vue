<script setup>
import { onMounted, ref, watch } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'

const props = defineProps({
  organizationId: { type: [String, Number], required: true }
})

const organizationStore = useOrganizationStore()
const isLoadingLocal = ref(false)
const error = ref(null)

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
watch(() => props.organizationId, load)

const org = () => organizationStore.currentOrganization
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

      <div v-if="org().banner" class="rounded-lg overflow-hidden border border-neutral-200">
        <img :src="org().banner" :alt="org().name" class="w-full max-h-56 object-cover" />
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

      <div v-if="org().notes" class="bg-white rounded-lg border border-neutral-200 p-4">
        <h3 class="font-semibold mb-3">Notes</h3>
        <p class="text-sm whitespace-pre-wrap">{{ org().notes }}</p>
      </div>
    </div>
  </div>
  
</template>
