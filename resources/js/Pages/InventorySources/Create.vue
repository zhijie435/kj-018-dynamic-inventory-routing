<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    code: '',
    name: '',
    type: 'warehouse',
    country: '',
    city: '',
    address: '',
    timezone: 'UTC',
    priority: 0,
    is_active: true,
});

function submit() {
    form.post(route('inventory-sources.store'), {
        onSuccess: () => {},
    });
}
</script>

<template>
    <Head title="Create Inventory Source" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Create Inventory Source
                </h2>
                <Link :href="route('inventory-sources.index')">
                    <SecondaryButton>Back</SecondaryButton>
                </Link>
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
                                    placeholder="e.g. US_WEST_WAREHOUSE"
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
                                    placeholder="e.g. US West Warehouse"
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
                                    placeholder="e.g. US"
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
                                    placeholder="e.g. Los Angeles"
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
                                    placeholder="Full address..."
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

                        <div class="flex items-center justify-end gap-4 border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <Link :href="route('inventory-sources.index')">
                                <SecondaryButton type="button">Cancel</SecondaryButton>
                            </Link>
                            <PrimaryButton type="submit" :disabled="form.processing">
                                Create Inventory Source
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
