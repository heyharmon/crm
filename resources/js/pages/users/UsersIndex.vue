<script setup>
import { ref, onMounted } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import api from '@/services/api'

const users = ref([])
const invitations = ref([])
const loading = ref(false)
const error = ref(null)
const currentUser = ref(null)
const deletingUserId = ref(null)

const showInviteModal = ref(false)
const inviteForm = ref({
    email: '',
    role: 'guest'
})
const invitationUrl = ref('')
const inviteLoading = ref(false)
const inviteError = ref('')
const copiedId = ref(null)

const fetchUsers = async () => {
    loading.value = true
    error.value = null
    try {
        users.value = await api.get('/users')
    } catch (err) {
        error.value = err?.message || 'Failed to load users.'
        users.value = []
    } finally {
        loading.value = false
    }
}

const fetchInvitations = async () => {
    try {
        invitations.value = await api.get('/invitations')
    } catch (err) {
        console.error('Failed to load invitations:', err)
        invitations.value = []
    }
}

onMounted(async () => {
    const storedUser = localStorage.getItem('user')
    if (storedUser) {
        currentUser.value = JSON.parse(storedUser)
    }
    fetchUsers()
    fetchInvitations()
})

const openInviteModal = () => {
    showInviteModal.value = true
    inviteForm.value = {
        email: '',
        role: 'guest'
    }
    invitationUrl.value = ''
    inviteError.value = ''
}

const closeInviteModal = () => {
    showInviteModal.value = false
    inviteForm.value = {
        email: '',
        role: 'guest'
    }
    invitationUrl.value = ''
    inviteError.value = ''
}

const createInvitation = async () => {
    if (!inviteForm.value.email) return

    inviteLoading.value = true
    inviteError.value = ''
    try {
        const response = await api.post('/invitations', inviteForm.value)
        invitationUrl.value = response.url
        await fetchInvitations()
    } catch (err) {
        inviteError.value = err?.message || 'Failed to create invitation'
    } finally {
        inviteLoading.value = false
    }
}

const copyInvitationUrl = async (url, id = null) => {
    try {
        await navigator.clipboard.writeText(url)
        if (id) {
            copiedId.value = id
            setTimeout(() => {
                copiedId.value = null
            }, 2000)
        }
    } catch (error) {
        console.error('Failed to copy:', error)
    }
}

const getInvitationUrl = (invitation) => {
    return `${window.location.origin}/register?token=${invitation.token}&email=${encodeURIComponent(invitation.email)}`
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const deleteUser = async (userId) => {
    if (!confirm('Are you sure you want to delete this user? This will also delete all their website design reviews.')) {
        return
    }

    deletingUserId.value = userId
    try {
        await api.delete(`/users/${userId}`)
        await fetchUsers()
    } catch (err) {
        error.value = err?.message || 'Failed to delete user.'
    } finally {
        deletingUserId.value = null
    }
}

const isAdmin = () => {
    return currentUser.value?.role === 'admin'
}
</script>

<template>
    <DefaultLayout>
        <div class="container mx-auto px-4 py-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-neutral-900">Users</h1>
                    <p class="text-sm text-neutral-500">Manage user accounts and send invitations</p>
                </div>
                <Button @click="openInviteModal">Invite User</Button>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-200 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-neutral-900">All Users</h2>
                        <div v-if="loading" class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Loading…</div>
                    </div>
                    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
                </div>

                <div class="hidden md:block">
                    <table class="min-w-full divide-y divide-neutral-200">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Name</th>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Role</th>
                                <th class="px-4 py-3 text-left">Created</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            <tr
                                v-for="user in users"
                                :key="user.id"
                                class="hover:bg-neutral-50/60 cursor-pointer"
                                @click="$router.push({ name: 'users.show', params: { id: user.id } })"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ user.name }}</td>
                                <td class="px-4 py-3 text-sm text-neutral-600">{{ user.email }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                            user.role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800'
                                        ]"
                                    >
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-neutral-600">{{ formatDate(user.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Button size="sm" variant="outline" @click.stop="$router.push({ name: 'users.show', params: { id: user.id } })">
                                            View
                                        </Button>
                                        <Button
                                            v-if="isAdmin() && currentUser?.id !== user.id"
                                            size="sm"
                                            variant="outline"
                                            class="text-red-600 hover:bg-red-50 hover:border-red-300"
                                            :disabled="deletingUserId === user.id"
                                            @click.stop="deleteUser(user.id)"
                                        >
                                            {{ deletingUserId === user.id ? 'Deleting...' : 'Delete' }}
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!users.length && !loading">
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-500">No users found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-4 px-4 py-4 md:hidden">
                    <div
                        v-for="user in users"
                        :key="`card-${user.id}`"
                        class="rounded-2xl border border-neutral-200 bg-neutral-50/80 p-4 shadow-sm shadow-neutral-200/40 cursor-pointer"
                        @click="$router.push({ name: 'users.show', params: { id: user.id } })"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-base font-semibold text-neutral-900">{{ user.name }}</div>
                                <div class="mt-1 text-sm text-neutral-600">{{ user.email }}</div>
                            </div>
                            <span
                                :class="[
                                    'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                    user.role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800'
                                ]"
                            >
                                {{ user.role }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <div class="text-xs text-neutral-500">Created {{ formatDate(user.created_at) }}</div>
                            <div class="flex items-center gap-2">
                                <Button size="sm" variant="outline" @click.stop="$router.push({ name: 'users.show', params: { id: user.id } })"> View </Button>
                                <Button
                                    v-if="isAdmin() && currentUser?.id !== user.id"
                                    size="sm"
                                    variant="outline"
                                    class="text-red-600 hover:bg-red-50 hover:border-red-300"
                                    :disabled="deletingUserId === user.id"
                                    @click.stop="deleteUser(user.id)"
                                >
                                    {{ deletingUserId === user.id ? 'Deleting...' : 'Delete' }}
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="!users.length && !loading"
                        class="rounded-xl border border-dashed border-neutral-300 bg-white/60 p-6 text-center text-sm text-neutral-500"
                    >
                        No users found.
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-lg border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-200 px-4 py-3">
                    <h2 class="text-lg font-semibold text-neutral-900">Pending Invitations</h2>
                </div>

                <div class="hidden md:block">
                    <table class="min-w-full divide-y divide-neutral-200">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Role</th>
                                <th class="px-4 py-3 text-left">Expires</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            <tr v-for="invitation in invitations" :key="invitation.id" class="hover:bg-neutral-50/60">
                                <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ invitation.email }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                            invitation.role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800'
                                        ]"
                                    >
                                        {{ invitation.role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-neutral-600">{{ formatDate(invitation.expires_at) }}</td>
                                <td class="px-4 py-3">
                                    <Button size="sm" variant="outline" @click="copyInvitationUrl(getInvitationUrl(invitation), invitation.id)">
                                        {{ copiedId === invitation.id ? 'Copied!' : 'Copy URL' }}
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="!invitations.length">
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-neutral-500">No pending invitations.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-4 px-4 py-4 md:hidden">
                    <div
                        v-for="invitation in invitations"
                        :key="`inv-card-${invitation.id}`"
                        class="rounded-2xl border border-neutral-200 bg-neutral-50/80 p-4 shadow-sm shadow-neutral-200/40"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-base font-semibold text-neutral-900">{{ invitation.email }}</div>
                                <div class="mt-1 text-xs text-neutral-500">Expires {{ formatDate(invitation.expires_at) }}</div>
                            </div>
                            <span
                                :class="[
                                    'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                    invitation.role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800'
                                ]"
                            >
                                {{ invitation.role }}
                            </span>
                        </div>
                        <div class="mt-3">
                            <Button size="sm" variant="outline" class="w-full" @click="copyInvitationUrl(getInvitationUrl(invitation), invitation.id)">
                                {{ copiedId === invitation.id ? 'Copied!' : 'Copy Invitation URL' }}
                            </Button>
                        </div>
                    </div>
                    <div
                        v-if="!invitations.length"
                        class="rounded-xl border border-dashed border-neutral-300 bg-white/60 p-6 text-center text-sm text-neutral-500"
                    >
                        No pending invitations.
                    </div>
                </div>
            </div>
        </div>

        <!-- Invite Modal -->
        <Teleport to="body">
            <transition name="modal">
                <div v-if="showInviteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeInviteModal">
                    <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                        <h2 class="mb-4 text-xl font-semibold text-neutral-900">Invite User</h2>

                        <div v-if="!invitationUrl" class="space-y-4">
                            <div>
                                <label for="email" class="mb-1 block text-sm font-medium text-neutral-700">Email</label>
                                <Input id="email" v-model="inviteForm.email" type="email" placeholder="user@example.com" />
                            </div>

                            <div>
                                <label for="role" class="mb-1 block text-sm font-medium text-neutral-700">Role</label>
                                <select
                                    id="role"
                                    v-model="inviteForm.role"
                                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                                >
                                    <option value="guest">Guest</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>

                            <div v-if="inviteError" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">
                                {{ inviteError }}
                            </div>

                            <div class="flex gap-2">
                                <Button class="flex-1" :disabled="inviteLoading || !inviteForm.email" @click="createInvitation">
                                    {{ inviteLoading ? 'Creating...' : 'Create Invitation' }}
                                </Button>
                                <Button variant="outline" @click="closeInviteModal">Cancel</Button>
                            </div>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">Invitation created successfully!</div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-neutral-700">Invitation URL</label>
                                <div class="flex items-center gap-2">
                                    <Input :model-value="invitationUrl" readonly class="flex-1 bg-neutral-50" />
                                    <Button @click="copyInvitationUrl(invitationUrl, 'modal')">
                                        {{ copiedId === 'modal' ? 'Copied!' : 'Copy URL' }}
                                    </Button>
                                </div>
                            </div>

                            <Button variant="outline" class="w-full" @click="closeInviteModal">Close</Button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </DefaultLayout>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
