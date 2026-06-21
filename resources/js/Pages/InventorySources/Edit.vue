<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    inventorySource: Object,
});

const confirmingDelete = ref(false);

const form = useForm({
    code: props.inventorySource.code,
    name: props.inventorySource.name,
    type: props.inventorySource.type,
    country: props.inventorySource.country || '',
    city: props.inventorySource.city || '',
    address: props.inventorySource.address || '',
    timezone: props.inventorySource.timezone || 'UTC',
    priority: props.inventorySource.priority,
    is_active: props.inventorySource.is_active,
});

function submit() {
    form.put(route('inventory-sources.update', props.inventorySource.id), {
        onSuccess: () => {},
    });
}

function confirmDelete() {
    confirmingDelete.value = true;
}

function deleteInventorySource() {
    router.delete(route('inventory-sources.destroy', props.inventorySource.id), {
        onSuccess: () => {},
    });
}
</script>

<template>
    <Head :title="'Edit: ' + inventorySource.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit Inventory Source: {{ inventorySource.name }}
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('inventory-sources.show', inventorySource.id)">
                        <SecondaryButton>View</SecondaryButton>
                    </Link>
                    <Link :href="route('inventory-sources.index')">
                        <SecondaryButton>Back</SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Source Information</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                            <div>
                                <InputLabel for="code" value="Source Code" />
                                <input
                                    id="code"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.code"
                                />
                                <InputError class="mt-2" :message="form.errors.code" />
                            </div>

                            <div>
                                <InputLabel for="name" value="Source Name" />
                                <input
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.name"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="type" value="Type" />
                                <select
                                    id="type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.type"
                                >
                                    <option value="warehouse">Warehouse</option>
                                    <option value="dropship">Dropship</option>
                                    <option value="store">Store</option>
                                    <option value="third_party">Third Party</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.type" />
                            </div>

                            <div>
                                <InputLabel for="priority" value="Priority" />
                                <input
                                    id="priority"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="999.99"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.priority"
                                />
                                <InputError class="mt-2" :message="form.errors.priority" />
                            </div>

                            <div>
                                <InputLabel for="country" value="Country" />
                                <input
                                    id="country"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.country"
                                />
                                <InputError class="mt-2" :message="form.errors.country" />
                            </div>

                            <div>
                                <InputLabel for="city" value="City" />
                                <input
                                    id="city"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.city"
                                />
                                <InputError class="mt-2" :message="form.errors.city" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="address" value="Address" />
                                <input
                                    id="address"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.address"
                                />
                                <InputError class="mt-2" :message="form.errors.address" />
                            </div>

                            <div>
                                <InputLabel for="timezone" value="Timezone" />
                                <select
                                    id="timezone"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.timezone"
                                >
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">America/New_York (EST)</option>
                                    <option value="America/Los_Angeles">America/Los_Angeles (PST)</option>
                                    <option value="America/Sao_Paulo">America/Sao_Paulo (BRT)</option>
                                    <option value="Europe/London">Europe/London (GMT)</option>
                                    <option value="Europe/Paris">Europe/Paris (CET)</option>
                                    <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
                                    <option value="Asia/Shanghai">Asia/Shanghai (CST)</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.timezone" />
                            </div>

                            <div>
                                <InputLabel for="is_active" value="Status" />
                                <div class="mt-2 flex items-center">
                                    <Checkbox id="is_active" v-model:checked="form.is_active" />
                                    <label for="is_active" class="ml-2 text-sm text-gray-600">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <button
                                type="button"
                                @click="confirmDelete"
                                class="text-sm text-red-600 hover:text-red-900"
                            >
                                Delete Inventory Source
                            </button>
                            <div class="flex items-center gap-4">
                                <Link :href="route('inventory-sources.index')">
                                    <SecondaryButton type="button">Cancel</SecondaryButton>
                                </Link>
                                <PrimaryButton type="submit" :disabled="form.processing">
                                    Save Changes
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div v-if="confirmingDelete" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                <div class="inline-block overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">
                                    Delete Inventory Source
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete this inventory source? This will also unbind it from all channels.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <DangerButton type="button" class="sm:ml-3" @click="deleteInventorySource">
                            Delete
                        </DangerButton>
                        <SecondaryButton type="button" @click="confirmingDelete = false">
                            Cancel
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
