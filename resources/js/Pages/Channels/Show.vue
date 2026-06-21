<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    channel: Object,
});
</script>

<template>
    <Head :title="channel.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ channel.name }}
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('channels.edit', channel.id)">
                        <PrimaryButton>Edit</PrimaryButton>
                    </Link>
                    <Link :href="route('channels.index')">
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
                                <h3 class="text-lg font-medium text-gray-900">Channel Details</h3>
                            </div>
                            <div class="p-6">
                                <dl class="space-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Code</dt>
                                        <dd class="mt-1 font-mono text-sm text-gray-900">{{ channel.code }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ channel.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Region</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ channel.region || 'Not set' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Currency</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ channel.currency }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Locale</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ channel.locale }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="channel.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                            >
                                                {{ channel.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div v-if="channel.description">
                                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ channel.description }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-gray-900">Bound Inventory Sources</h3>
                                    <span class="text-sm text-gray-500">
                                        {{ channel.inventory_sources.length }} sources
                                    </span>
                                </div>
                            </div>
                            <div class="p-6">
                                <div v-if="channel.inventory_sources.length === 0" class="text-center py-8 text-gray-500">
                                    <p class="mb-4">No inventory sources bound to this channel.</p>
                                    <Link :href="route('channels.edit', channel.id)">
                                        <PrimaryButton>Add Inventory Sources</PrimaryButton>
                                    </Link>
                                </div>

                                <div v-else class="space-y-3">
                                    <div
                                        v-for="(source, index) in channel.inventory_sources"
                                        :key="source.id"
                                        class="flex items-center justify-between rounded-lg border p-4"
                                        :class="{
                                            'border-indigo-500 bg-indigo-50': source.pivot.is_primary,
                                            'border-gray-200': !source.pivot.is_primary,
                                        }"
                                    >
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold"
                                                :class="source.pivot.is_primary ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'"
                                            >
                                                {{ index + 1 }}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-gray-900">{{ source.name }}</span>
                                                    <span class="font-mono text-xs text-gray-500">({{ source.code }})</span>
                                                    <span
                                                        v-if="source.pivot.is_primary"
                                                        class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-medium text-white"
                                                    >
                                                        Primary
                                                    </span>
                                                </div>
                                                <div class="mt-1 text-sm text-gray-500">
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                        {{ source.type }}
                                                    </span>
                                                    <span v-if="source.country || source.city" class="ml-2">
                                                        {{ source.city }}{{ source.city && source.country ? ', ' : '' }}{{ source.country }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm text-gray-500">
                                                Priority: #{{ source.pivot.sort_order + 1 }}
                                            </div>
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
