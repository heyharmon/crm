<script setup>
import { ref, onMounted } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import api from '@/services/api'

const options = ref([])
const loading = ref(false)
const error = ref(null)

const newOption = ref({
    name: '',
    score: 3,
    description: ''
})

const editingId = ref(null)
const editingOption = ref({
    name: '',
    score: 3,
    description: ''
})

const deleteMode = ref({})
const deleteAction = ref({})
const deleteReassignTo = ref({})

const fetchOptions = async () => {
    loading.value = true
    error.value = null
    try {
        options.value = await api.get('/website-rating-options')
    } catch (err) {
        error.value = err?.message || 'Failed to load website rating options.'
        options.value = []
    } finally {
        loading.value = false
    }
}

onMounted(fetchOptions)

const resetNewOption = () => {
    newOption.value = {
        name: '',
        score: 3,
        description: ''
    }
}

const createOption = async () => {
    if (!newOption.value.name) return
    try {
        await api.post('/website-rating-options', {
            name: newOption.value.name,
            score: Number(newOption.value.score),
            description: newOption.value.description || null
        })
        resetNewOption()
        await fetchOptions()
    } catch (err) {
        console.error('Failed to create rating option:', err)
        error.value = err?.message || 'Failed to create rating option.'
    }
}

const startEdit = (option) => {
    editingId.value = option.id
    editingOption.value = {
        name: option.name,
        score: option.score,
        description: option.description || ''
    }
}

const cancelEdit = () => {
    editingId.value = null
    editingOption.value = {
        name: '',
        score: 3,
        description: ''
    }
}

const updateOption = async (optionId) => {
    try {
        await api.put(`/website-rating-options/${optionId}`, {
            name: editingOption.value.name,
            score: Number(editingOption.value.score),
            description: editingOption.value.description || null
        })
        cancelEdit()
        await fetchOptions()
    } catch (err) {
        console.error('Failed to update rating option:', err)
        error.value = err?.message || 'Failed to update rating option.'
    }
}

const toggleDeleteMode = (id) => {
    deleteMode.value[id] = !deleteMode.value[id]
    if (deleteMode.value[id] && !deleteAction.value[id]) {
        deleteAction.value[id] = 'delete_ratings'
    }
}

const confirmDelete = async (option) => {
    const action = deleteAction.value[option.id] || 'delete_ratings'
    const payload = { action }
    if (action === 'reassign') {
        payload.reassign_to_id = deleteReassignTo.value[option.id] || null
        if (!payload.reassign_to_id) {
            return
        }
    }

    try {
        await api.delete(`/website-rating-options/${option.id}`, { data: payload })
        deleteMode.value[option.id] = false
        deleteAction.value[option.id] = 'delete_ratings'
        deleteReassignTo.value[option.id] = null
        await fetchOptions()
    } catch (err) {
        console.error('Failed to delete rating option:', err)
        error.value = err?.message || 'Failed to delete rating option.'
    }
}
</script>

<template>
    <DefaultLayout>
        <div class="container mx-auto py-8 px-4">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-neutral-900">Website Rating Options</h1>
                    <p class="text-sm text-neutral-500">Manage the reusable options users can select when rating organization websites.</p>
                </div>
            </div>

            <div class="mb-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-neutral-900 mb-4">Add New Option</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-neutral-700">Name</label>
                        <Input v-model="newOption.name" placeholder="e.g., Excellent" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-neutral-700">Score (1-5)</label>
                        <Input v-model.number="newOption.score" type="number" min="1" max="5" />
                    </div>
                    <div class="md:col-span-4">
                        <label class="mb-1 block text-sm font-medium text-neutral-700">Description (optional)</label>
                        <Input v-model="newOption.description" placeholder="Brief guidance for raters" />
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <Button :disabled="!newOption.name" @click="createOption">Add Option</Button>
                </div>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-200 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-neutral-900">Existing Options</h2>
                        <div v-if="loading" class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Loading…</div>
                    </div>
                    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
                </div>
                <div class="hidden md:block">
                    <table class="min-w-full divide-y divide-neutral-200">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Name</th>
                                <th class="px-4 py-3 text-left">Score</th>
                                <th class="px-4 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-left">Ratings</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            <tr v-for="option in options" :key="option.id" class="align-top hover:bg-neutral-50/60">
                                <td class="px-4 py-3">
                                    <div v-if="editingId === option.id" class="space-y-2">
                                        <Input v-model="editingOption.name" />
                                    </div>
                                    <div v-else>
                                        <div class="text-sm font-semibold text-neutral-900">{{ option.name }}</div>
                                        <div class="text-xs text-neutral-400">Slug: {{ option.slug }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="editingId === option.id">
                                        <Input v-model.number="editingOption.score" type="number" min="1" max="5" />
                                    </div>
                                    <div v-else class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-sm font-semibold text-neutral-800">
                                        {{ option.score }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="editingId === option.id">
                                        <Input v-model="editingOption.description" placeholder="Optional description" />
                                    </div>
                                    <div v-else class="text-sm text-neutral-600">
                                        {{ option.description || '—' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-neutral-700">
                                    {{ option.ratings_count || 0 }}
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="editingId === option.id" class="flex flex-wrap gap-2">
                                        <Button size="sm" @click="updateOption(option.id)">Save</Button>
                                        <Button size="sm" variant="outline" @click="cancelEdit">Cancel</Button>
                                    </div>
                                    <div v-else class="flex flex-wrap gap-2">
                                        <Button size="sm" variant="outline" @click="startEdit(option)">Edit</Button>
                                        <Button size="sm" variant="outline" @click="toggleDeleteMode(option.id)">Delete</Button>
                                    </div>
                                    <div v-if="deleteMode[option.id]" class="mt-3 space-y-3 rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                                        <div class="text-sm font-medium text-neutral-800">Delete option</div>
                                        <label class="flex items-center gap-2 text-sm text-neutral-600">
                                            <input type="radio" :name="`delete-${option.id}`" value="delete_ratings" v-model="deleteAction[option.id]" />
                                            <span>Remove this option and delete associated ratings</span>
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-neutral-600">
                                            <input type="radio" :name="`delete-${option.id}`" value="reassign" v-model="deleteAction[option.id]" />
                                            <span>Move ratings to another option</span>
                                        </label>
                                        <div v-if="deleteAction[option.id] === 'reassign'" class="pl-6">
                                            <select class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" v-model="deleteReassignTo[option.id]">
                                                <option :value="null">Select option</option>
                                                <option
                                                    v-for="candidate in options.filter((o) => o.id !== option.id)"
                                                    :key="candidate.id"
                                                    :value="candidate.id"
                                                >
                                                    {{ candidate.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" @click="toggleDeleteMode(option.id)">Cancel</Button>
                                            <Button
                                                size="sm"
                                                :disabled="deleteAction[option.id] === 'reassign' && !deleteReassignTo[option.id]"
                                                @click="confirmDelete(option)"
                                            >
                                                Confirm Delete
                                            </Button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!options.length && !loading">
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-500">No rating options found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="space-y-4 px-4 py-4 md:hidden">
                    <div
                        v-for="option in options"
                        :key="`card-${option.id}`"
                        class="rounded-2xl border border-neutral-200 bg-neutral-50/80 p-4 shadow-sm shadow-neutral-200/40"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div v-if="editingId === option.id" class="space-y-2">
                                    <Input v-model="editingOption.name" placeholder="Option name" />
                                </div>
                                <div v-else class="space-y-1">
                                    <div class="text-base font-semibold text-neutral-900">{{ option.name }}</div>
                                    <div class="text-xs uppercase tracking-wide text-neutral-400">Slug: {{ option.slug }}</div>
                                </div>
                            </div>
                            <div class="flex flex-shrink-0 items-center gap-2">
                                <div v-if="editingId === option.id" class="w-20">
                                    <Input v-model.number="editingOption.score" type="number" min="1" max="5" />
                                </div>
                                <div
                                    v-else
                                    class="inline-flex items-center rounded-full bg-neutral-900 text-xs font-semibold uppercase tracking-wide text-white"
                                >
                                    <span class="px-3 py-1.5">Score {{ option.score }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div v-if="editingId === option.id">
                                <Input v-model="editingOption.description" placeholder="Optional description" />
                            </div>
                            <p v-else class="text-sm text-neutral-600">
                                {{ option.description || 'No description provided.' }}
                            </p>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-neutral-500">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-3 py-1 font-semibold text-neutral-700"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-neutral-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-11a1 1 0 00-.894.553l-2 4a1 1 0 101.788.894L9.382 11h1.236l.488 1.447a1 1 0 101.788-.894l-2-4A1 1 0 0010 7z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <span class="text-sm font-semibold text-neutral-800">{{ option.ratings_count || 0 }}</span>
                                <span class="uppercase tracking-wide">ratings</span>
                            </div>
                            <div class="flex flex-1 justify-end gap-2">
                                <template v-if="editingId === option.id">
                                    <Button size="sm" class="flex-1" @click="updateOption(option.id)">Save</Button>
                                    <Button size="sm" variant="outline" class="flex-1" @click="cancelEdit">Cancel</Button>
                                </template>
                                <template v-else>
                                    <Button size="sm" variant="outline" class="flex-1" @click="startEdit(option)">Edit</Button>
                                    <Button size="sm" variant="outline" class="flex-1" @click="toggleDeleteMode(option.id)">Delete</Button>
                                </template>
                            </div>
                        </div>
                        <transition name="collapse">
                            <div v-if="deleteMode[option.id]" class="mt-4 space-y-3 rounded-xl border border-neutral-200 bg-white p-3 text-sm text-neutral-600">
                                <div class="text-sm font-semibold text-neutral-800">Delete option</div>
                                <label class="flex items-start gap-2">
                                    <input type="radio" :name="`delete-card-${option.id}`" value="delete_ratings" v-model="deleteAction[option.id]" />
                                    <span>Remove this option and delete associated ratings</span>
                                </label>
                                <label class="flex items-start gap-2">
                                    <input type="radio" :name="`delete-card-${option.id}`" value="reassign" v-model="deleteAction[option.id]" />
                                    <span>Move ratings to another option</span>
                                </label>
                                <div v-if="deleteAction[option.id] === 'reassign'" class="pl-6">
                                    <select class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" v-model="deleteReassignTo[option.id]">
                                        <option :value="null">Select option</option>
                                        <option
                                            v-for="candidate in options.filter((o) => o.id !== option.id)"
                                            :key="`card-reassign-${option.id}-${candidate.id}`"
                                            :value="candidate.id"
                                        >
                                            {{ candidate.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Button size="sm" variant="outline" class="flex-1" @click="toggleDeleteMode(option.id)">Cancel</Button>
                                    <Button
                                        size="sm"
                                        class="flex-1"
                                        :disabled="deleteAction[option.id] === 'reassign' && !deleteReassignTo[option.id]"
                                        @click="confirmDelete(option)"
                                    >
                                        Confirm Delete
                                    </Button>
                                </div>
                            </div>
                        </transition>
                    </div>
                    <div
                        v-if="!options.length && !loading"
                        class="rounded-xl border border-dashed border-neutral-300 bg-white/60 p-6 text-center text-sm text-neutral-500"
                    >
                        No rating options found.
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>

<style scoped>
.collapse-enter-active,
.collapse-leave-active {
    transition: max-height 0.25s ease, opacity 0.2s ease;
    overflow: hidden;
}
.collapse-enter-from,
.collapse-leave-to {
    max-height: 0;
    opacity: 0;
}
.collapse-enter-to,
.collapse-leave-from {
    max-height: 800px;
    opacity: 1;
}
</style>
