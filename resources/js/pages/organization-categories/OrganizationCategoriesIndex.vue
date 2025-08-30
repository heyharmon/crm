<script setup>
import { ref, onMounted, computed } from 'vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import api from '@/services/api';

const categories = ref([]);
const newCategory = ref('');
const editingId = ref(null);
const editingName = ref('');
const showingDelete = ref({});
const deleteAction = ref({}); // 'disassociate' | 'reassign' | 'destroy'
const reassignTo = ref({});

const fetchCategories = async () => {
  categories.value = await api.get('/organization-categories');
};

onMounted(fetchCategories);

const createCategory = async () => {
  if (!newCategory.value) return;
  await api.post('/organization-categories', { name: newCategory.value });
  newCategory.value = '';
  await fetchCategories();
};

const startEdit = (category) => {
  editingId.value = category.id;
  editingName.value = category.name;
};

const updateCategory = async (id) => {
  await api.put(`/organization-categories/${id}`, { name: editingName.value });
  editingId.value = null;
  editingName.value = '';
  await fetchCategories();
};

const deleteCategory = async (id) => {
  // Default simple delete (disassociate) fallback
  await api.delete(`/organization-categories/${id}`, { data: { action: 'disassociate' } });
  await fetchCategories();
};

const toggleDeleteOptions = (id) => {
  showingDelete.value[id] = !showingDelete.value[id];
  if (showingDelete.value[id] && !deleteAction.value[id]) {
    deleteAction.value[id] = 'disassociate';
  }
};

const confirmDelete = async (cat) => {
  const action = deleteAction.value[cat.id] || 'disassociate';
  const payload = { action };
  if (action === 'reassign') {
    payload.reassign_to_id = reassignTo.value[cat.id] || null;
  }
  await api.delete(`/organization-categories/${cat.id}`, { data: payload });
  // reset UI state
  showingDelete.value[cat.id] = false;
  deleteAction.value[cat.id] = 'disassociate';
  reassignTo.value[cat.id] = null;
  await fetchCategories();
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Organization Categories</h1>
      <div class="mb-6 flex space-x-2">
        <Input v-model="newCategory" placeholder="New category name" />
        <Button @click="createCategory">Add</Button>
      </div>
      <div class="bg-white rounded-lg shadow-sm border border-neutral-200">
        <table class="min-w-full divide-y divide-neutral-200">
          <tbody class="divide-y divide-neutral-200">
            <tr v-for="cat in categories" :key="cat.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3">
                <div v-if="editingId === cat.id" class="flex space-x-2">
                  <Input v-model="editingName" />
                  <Button @click="updateCategory(cat.id)">Save</Button>
                  <Button variant="outline" @click="editingId = null">Cancel</Button>
                </div>
                <div v-else class="flex justify-between items-center">
                  <span>{{ cat.name }}</span>
                  <div class="space-x-2">
                    <Button variant="outline" @click="startEdit(cat)">Edit</Button>
                    <Button variant="outline" @click="toggleDeleteOptions(cat.id)">Delete</Button>
                  </div>
                </div>
                <div v-if="showingDelete[cat.id]" class="mt-3 p-3 border rounded bg-neutral-50">
                  <div class="text-sm font-medium mb-2">Delete options</div>
                  <div class="space-y-2 text-sm">
                    <label class="flex items-center space-x-2">
                      <input type="radio" :name="`del-${cat.id}`" value="disassociate" v-model="deleteAction[cat.id]">
                      <span>Disassociate from organizations (set to none)</span>
                    </label>
                    <label class="flex items-center space-x-2">
                      <input type="radio" :name="`del-${cat.id}`" value="reassign" v-model="deleteAction[cat.id]">
                      <span>Reassign organizations to another category</span>
                    </label>
                    <div v-if="deleteAction[cat.id] === 'reassign'" class="pl-6">
                      <select class="border rounded px-2 py-1" v-model="reassignTo[cat.id]">
                        <option :value="null">Select category</option>
                        <option v-for="c in categories.filter(x => x.id !== cat.id)" :key="c.id" :value="c.id">{{ c.name }}</option>
                      </select>
                    </div>
                    <label class="flex items-center space-x-2">
                      <input type="radio" :name="`del-${cat.id}`" value="destroy" v-model="deleteAction[cat.id]">
                      <span>Destroy organizations in this category</span>
                    </label>
                  </div>
                  <div class="mt-3 space-x-2">
                    <Button variant="outline" @click="showingDelete[cat.id] = false">Cancel</Button>
                    <Button 
                      :disabled="deleteAction[cat.id] === 'reassign' && !reassignTo[cat.id]"
                      @click="confirmDelete(cat)"
                    >Confirm Delete</Button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </DefaultLayout>
</template>
