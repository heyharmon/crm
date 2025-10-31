<script setup>
import { ref, watch, onMounted } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import api from '@/services/api'

const props = defineProps({
    organization: {
        type: Object,
        default: () => ({})
    },
    isLoading: {
        type: Boolean,
        default: false
    },
    showActions: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['submit'])

const form = ref({
    name: '',
    source: '',
    banner: '',
    score: '',
    reviews: '',
    street: '',
    city: '',
    state: '',
    country: '',
    website: '',
    phone: '',
    organization_category_id: '',
    notes: ''
})

const errors = ref({})

watch(
    () => props.organization,
    (newOrg) => {
        if (newOrg) {
            Object.keys(form.value).forEach((key) => {
                form.value[key] = newOrg[key] || ''
            })
            if (newOrg.category) {
                form.value.organization_category_id = newOrg.category.id
            }
        }
    },
    { immediate: true }
)

const categories = ref([])
onMounted(async () => {
    try {
        categories.value = await api.get('/organization-categories')
    } catch (e) {
        console.error('Failed to load categories', e)
    }
})

const submitForm = () => {
    errors.value = {}
    emit('submit', { ...form.value })
}

const validateWebsite = () => {
    if (form.value.website && !form.value.website.match(/^https?:\/\//)) {
        form.value.website = 'https://' + form.value.website
    }
}

defineExpose({ submitForm })
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Basic Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Organization Name *</label>
                    <Input v-model="form.name" required placeholder="Enter organization name" />
                    <span v-if="errors.name" class="text-red-500 text-sm">{{ errors.name[0] }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Source</label>
                    <Input v-model="form.source" placeholder="e.g., HubSpot, Google Maps, NCUA" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Category</label>
                    <select
                        v-model="form.organization_category_id"
                        class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">None</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Banner Image URL</label>
                    <Input v-model="form.banner" type="url" placeholder="https://example.com/image.jpg" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Rating (0-5)</label>
                    <Input v-model="form.score" type="number" step="0.1" min="0" max="5" placeholder="4.5" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Number of Reviews</label>
                    <Input v-model="form.reviews" type="number" min="0" placeholder="123" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Contact Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Phone</label>
                    <Input v-model="form.phone" type="tel" placeholder="(555) 123-4567" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Website</label>
                    <Input v-model="form.website" type="url" @blur="validateWebsite" placeholder="https://example.com" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Address</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Street Address</label>
                    <Input v-model="form.street" placeholder="123 Main St" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">City</label>
                    <Input v-model="form.city" placeholder="Anytown" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">State</label>
                    <Input v-model="form.state" placeholder="Utah" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Country</label>
                    <Input v-model="form.country" placeholder="United States" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Notes</h3>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Additional Notes</label>
                <textarea
                    v-model="form.notes"
                    rows="4"
                    class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Add any additional notes about this organization..."
                ></textarea>
            </div>
        </div>

        <div v-if="showActions" class="flex justify-end space-x-2">
            <router-link to="/organizations">
                <Button variant="outline">Cancel</Button>
            </router-link>
            <Button type="submit" :disabled="isLoading">
                {{ isLoading ? 'Saving...' : 'Save Organization' }}
            </Button>
        </div>
    </form>
</template>
