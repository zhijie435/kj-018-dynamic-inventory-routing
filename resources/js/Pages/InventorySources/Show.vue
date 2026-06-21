<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    inventorySource: Object,
});
</script>

<template>
    <Head :title="inventorySource.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ inventorySource.name }}
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('inventory-sources.edit', inventorySource.id)">
                        <PrimaryButton>Edit</PrimaryButton>
                    </Link>
                    <Link :href="route('inventory-sources.index')">
                        <SecondaryButton>Back</SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-1">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                                <h3 class="text-lg font-medium text-gray-900">Source Details</h3>
                            </div>
                            <div class="p-6">
                                <dl class="space-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Code</dt>
                                        <dd class="mt-1 font-mono text-sm text-gray-900">{{ inventorySource.code }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ inventorySource.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Type</dt>
                                        <dd class="mt-1">
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 capitalize">
                                                {{ inventorySource.type.replace('_', ' ') }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Priority</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ inventorySource.priority }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Location</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            {{ inventorySource.address || 'No address' }}
                                            <div v-if="inventorySource.city || inventorySource.country" class="text-gray-500">
                                                {{ inventorySource.city }}{{ inventorySource.city && inventorySource.country ? ', ' : '' }}{{ inventorySource.country }}
                                            </div>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Timezone</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ inventorySource.timezone || 'UTC' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="inventorySource.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                            >
                                                {{ inventorySource.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-gray-900">Bound Channels</h3>
                                    <span class="text-sm text-gray-500">
                                        Used by {{ inventorySource.channels.length }} channels
                                    </span>
                                </div>
                            </div>
                            <div class="p-6">
                                <div v-if="inventorySource.channels.length === 0" class="text-center py-8 text-gray-500">
                                    <p>This inventory source is not bound to any channels yet.</p>
                                    <p class="mt-1 text-sm">Go to a channel's edit page to bind this inventory source.</p>
                                    <div class="mt-4">
                                        <Link :href="route('channels.index')">
                                            <PrimaryButton>Manage Channels</PrimaryButton>
                                        </Link>
                                    </div>
                                </div>

                                <div v-else class="space-y-3">
                                    <div
                                        v-for="channel in inventorySource.channels"
                                        :key="channel.id"
                                        class="flex items-center justify-between rounded-lg border border-gray-200 p-4"
                                    >
                                        <div class="flex items-center gap-4">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100">
                                                <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <Link :href="route('channels.show', channel.id)" class="font-medium text-gray-900 hover:text-indigo-600">
                                                        {{ channel.name }}
                                                    </Link>
                                                    <span class="font-mono text-xs text-gray-500">({{ channel.code }})</span>
                                                    <span
                                                        v-if="channel.pivot.is_primary"
                                                        class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800"
                                                    >
                                                        Primary for this channel
                                                    </span>
                                                </div>
                                                <div class="mt-1 text-sm text-gray-500">
                                                    {{ channel.region || 'No region' }} &middot; {{ channel.currency }}
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <Link
                                                :href="route('channels.edit', channel.id)"
                                                class="text-sm text-indigo-600 hover:text-indigo-900"
                                            >
                                                Edit Channel
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
