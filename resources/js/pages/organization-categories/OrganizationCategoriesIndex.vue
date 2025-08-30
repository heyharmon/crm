<script setup>
import { ref, onMounted } from 'vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import api from '@/services/api';

const categories = ref([]);
const newCategory = ref('');
const editingId = ref(null);
const editingName = ref('');

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
  await api.delete(`/organization-categories/${id}`);
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
                    <Button variant="outline" @click="deleteCategory(cat.id)">Delete</Button>
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
