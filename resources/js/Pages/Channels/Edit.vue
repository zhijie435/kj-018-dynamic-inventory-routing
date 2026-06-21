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
    channel: Object,
    inventorySources: Array,
    boundInventorySourceIds: Array,
});

const confirmingDelete = ref(false);

const form = useForm({
    code: props.channel.code,
    name: props.channel.name,
    region: props.channel.region || '',
    currency: props.channel.currency,
    locale: props.channel.locale,
    description: props.channel.description || '',
    is_active: props.channel.is_active,
    inventory_source_ids: props.boundInventorySourceIds.map(s => ({
        id: s.id,
        is_primary: s.is_primary,
        sort_order: s.sort_order,
    })),
});

function toggleInventorySource(source) {
    const existingIndex = form.inventory_source_ids.findIndex(s => s.id === source.id);
    if (existingIndex > -1) {
        const wasPrimary = form.inventory_source_ids[existingIndex].is_primary;
        form.inventory_source_ids.splice(existingIndex, 1);
        if (wasPrimary && form.inventory_source_ids.length > 0) {
            form.inventory_source_ids[0].is_primary = true;
        }
    } else {
        form.inventory_source_ids.push({
            id: source.id,
            is_primary: form.inventory_source_ids.length === 0,
            sort_order: form.inventory_source_ids.length,
        });
    }
}

function isSourceSelected(sourceId) {
    return form.inventory_source_ids.some(s => s.id === sourceId);
}

function isPrimarySource(sourceId) {
    const source = form.inventory_source_ids.find(s => s.id === sourceId);
    return source?.is_primary;
}

function setPrimarySource(sourceId) {
    form.inventory_source_ids.forEach(s => {
        s.is_primary = s.id === sourceId;
    });
}

function getSelectedIndex(sourceId) {
    return form.inventory_source_ids.findIndex(s => s.id === sourceId);
}

function getSortOrder(sourceId) {
    const source = form.inventory_source_ids.find(s => s.id === sourceId);
    return source?.sort_order ?? 0;
}

function moveSource(sourceId, direction) {
    const index = form.inventory_source_ids.findIndex(s => s.id === sourceId);
    if (index === -1) return;

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= form.inventory_source_ids.length) return;

    const temp = form.inventory_source_ids[index];
    form.inventory_source_ids[index] = form.inventory_source_ids[newIndex];
    form.inventory_source_ids[newIndex] = temp;

    form.inventory_source_ids.forEach((s, i) => {
        s.sort_order = i;
    });
}

function submit() {
    form.put(route('channels.update', props.channel.id), {
        onSuccess: () => {},
    });
}

function confirmDelete() {
    confirmingDelete.value = true;
}

function deleteChannel() {
    router.delete(route('channels.destroy', props.channel.id), {
        onSuccess: () => {},
    });
}
</script>

<template>
    <Head :title="'Edit: ' + channel.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit Channel: {{ channel.name }}
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('channels.show', channel.id)">
                        <SecondaryButton>View</SecondaryButton>
                    </Link>
                    <Link :href="route('channels.index')">
                        <SecondaryButton>Back</SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Channel Information</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                            <div>
                                <InputLabel for="code" value="Channel Code" />
                                <input
                                    id="code"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.code"
                                />
                                <InputError class="mt-2" :message="form.errors.code" />
                            </div>

                            <div>
                                <InputLabel for="name" value="Channel Name" />
                                <input
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.name"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="region" value="Region" />
                                <select
                                    id="region"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.region"
                                >
                                    <option value="">Select Region</option>
                                    <option value="US">United States</option>
                                    <option value="BR">Brazil</option>
                                    <option value="EU">Europe</option>
                                    <option value="UK">United Kingdom</option>
                                    <option value="JP">Japan</option>
                                    <option value="APAC">Asia Pacific</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.region" />
                            </div>

                            <div>
                                <InputLabel for="currency" value="Currency" />
                                <select
                                    id="currency"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.currency"
                                >
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="BRL">BRL - Brazilian Real</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="GBP">GBP - British Pound</option>
                                    <option value="JPY">JPY - Japanese Yen</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.currency" />
                            </div>

                            <div>
                                <InputLabel for="locale" value="Locale" />
                                <select
                                    id="locale"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.locale"
                                >
                                    <option value="en_US">English (US)</option>
                                    <option value="pt_BR">Portuguese (Brazil)</option>
                                    <option value="es_ES">Spanish</option>
                                    <option value="de_DE">German</option>
                                    <option value="fr_FR">French</option>
                                    <option value="ja_JP">Japanese</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.locale" />
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

                            <div class="md:col-span-2">
                                <InputLabel for="description" value="Description" />
                                <textarea
                                    id="description"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="form.description"
                                />
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>
                        </div>

                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">Bound Inventory Sources</h3>
                                <span class="text-sm text-gray-500">
                                    {{ form.inventory_source_ids.length }} selected
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Manage which inventory sources are available for this channel. Set a primary source and adjust the routing order.
                            </p>
                        </div>

                        <div class="p-6">
                            <div v-if="inventorySources.length === 0" class="text-center py-8 text-gray-500">
                                No inventory sources available.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="source in inventorySources"
                                    :key="source.id"
                                    class="flex items-center justify-between rounded-lg border p-4 transition"
                                    :class="{
                                        'border-indigo-500 bg-indigo-50': isSourceSelected(source.id),
                                        'border-gray-200 hover:border-gray-300': !isSourceSelected(source.id),
                                    }"
                                >
                                    <div class="flex items-center gap-4">
                                        <Checkbox
                                            :checked="isSourceSelected(source.id)"
                                            @update:checked="toggleInventorySource(source)"
                                        />
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-gray-900">{{ source.name }}</span>
                                                <span class="font-mono text-xs text-gray-500">({{ source.code }})</span>
                                                <span
                                                    v-if="isSourceSelected(source.id) && isPrimarySource(source.id)"
                                                    class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-medium text-white"
                                                >
                                                    Primary
                                                </span>
                                            </div>
                                            <div class="mt-1 text-sm text-gray-500">
                                                {{ source.type }}
                                                <span v-if="source.country || source.city">
                                                    &middot; {{ source.city }}{{ source.city && source.country ? ', ' : '' }}{{ source.country }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="isSourceSelected(source.id)" class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500">
                                            Priority #{{ getSortOrder(source.id) + 1 }}
                                        </span>
                                        <button
                                            type="button"
                                            @click="setPrimarySource(source.id)"
                                            :disabled="isPrimarySource(source.id)"
                                            class="rounded-md px-2 py-1 text-xs font-medium transition"
                                            :class="isPrimarySource(source.id) ? 'bg-indigo-100 text-indigo-700 cursor-default' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                        >
                                            Set Primary
                                        </button>
                                        <div class="flex flex-col">
                                            <button
                                                type="button"
                                                @click="moveSource(source.id, 'up')"
                                                :disabled="getSortOrder(source.id) <= 0"
                                                class="rounded px-1 text-gray-500 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="moveSource(source.id, 'down')"
                                                :disabled="getSortOrder(source.id) >= form.inventory_source_ids.length - 1"
                                                class="rounded px-1 text-gray-500 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <button
                                type="button"
                                @click="confirmDelete"
                                class="text-sm text-red-600 hover:text-red-900"
                            >
                                Delete Channel
                            </button>
                            <div class="flex items-center gap-4">
                                <Link :href="route('channels.index')">
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
                                    Delete Channel
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete this channel? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <DangerButton type="button" class="sm:ml-3" @click="deleteChannel">
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
