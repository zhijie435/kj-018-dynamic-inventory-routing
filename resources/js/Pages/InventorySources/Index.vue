<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = defineProps({
    inventorySources: Object,
    filters: Object,
});
</script>

<template>
    <Head title="Inventory Sources" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Inventory Sources
                </h2>
                <Link :href="route('inventory-sources.create')">
                    <PrimaryButton>Create Inventory Source</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 bg-gray-50 p-6">
                        <form class="flex flex-wrap gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Search</label>
                                <input
                                    type="text"
                                    name="search"
                                    :value="filters.search"
                                    placeholder="Search by name or code..."
                                    class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Type</label>
                                <select
                                    name="type"
                                    :value="filters.type"
                                    class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">All Types</option>
                                    <option value="warehouse">Warehouse</option>
                                    <option value="dropship">Dropship</option>
                                    <option value="store">Store</option>
                                    <option value="third_party">Third Party</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <PrimaryButton type="submit">Filter</PrimaryButton>
                                <Link
                                    :href="route('inventory-sources.index')"
                                    class="ml-2 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50"
                                >
                                    Reset
                                </Link>
                            </div>
                        </form>
                    </div>

                    <div class="p-6 text-gray-900">
                        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-green-200 bg-green-50 p-4 text-green-700">
                            {{ page.props.flash.success }}
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Priority</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Channels</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="source in inventorySources.data" :key="source.id">
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="font-mono text-sm text-gray-900">{{ source.code }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <Link :href="route('inventory-sources.show', source.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                                {{ source.name }}
                                            </Link>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 capitalize">
                                                {{ source.type.replace('_', ' ') }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {{ source.city || '' }}{{ source.city && source.country ? ', ' : '' }}{{ source.country || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {{ source.priority }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="channel in source.channels.slice(0, 3)"
                                                    :key="channel.id"
                                                    class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                                                >
                                                    {{ channel.code }}
                                                </span>
                                                <span
                                                    v-if="source.channels.length > 3"
                                                    class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                                >
                                                    +{{ source.channels.length - 3 }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="source.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                            >
                                                {{ source.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <Link :href="route('inventory-sources.edit', source.id)" class="text-indigo-600 hover:text-indigo-900">Edit</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="inventorySources.data.length === 0">
                                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                            No inventory sources found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="inventorySources.links && inventorySources.links.length > 3" class="mt-6">
                            <div class="flex justify-between text-sm text-gray-500">
                                <span>
                                    Showing {{ inventorySources.from }} to {{ inventorySources.to }} of {{ inventorySources.total }} results
                                </span>
                                <div class="flex gap-2">
                                    <Link
                                        v-for="link in inventorySources.links"
                                        :key="link.label"
                                        :href="link.url || '#'"
                                        class="rounded-md border px-3 py-1"
                                        :class="{
                                            'bg-indigo-500 text-white border-indigo-500': link.active,
                                            'bg-white border-gray-300 text-gray-700 hover:bg-gray-50': !link.active && link.url,
                                            'bg-gray-100 border-gray-200 text-gray-400 cursor-not-allowed': !link.url,
                                        }"
                                        v-html="link.label"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
