<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = defineProps({
    channels: Object,
    filters: Object,
});
</script>

<template>
    <Head title="Channels" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Channels
                </h2>
                <Link :href="route('channels.create')">
                    <PrimaryButton>Create Channel</PrimaryButton>
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
                                <label class="block text-sm font-medium text-gray-700">Region</label>
                                <select
                                    name="region"
                                    :value="filters.region"
                                    class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">All Regions</option>
                                    <option value="US">United States</option>
                                    <option value="BR">Brazil</option>
                                    <option value="EU">Europe</option>
                                    <option value="APAC">Asia Pacific</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <PrimaryButton type="submit">Filter</PrimaryButton>
                                <Link
                                    :href="route('channels.index')"
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
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Region</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Currency</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Inventory Sources</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="channel in channels.data" :key="channel.id">
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="font-mono text-sm text-gray-900">{{ channel.code }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <Link :href="route('channels.show', channel.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                                {{ channel.name }}
                                            </Link>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {{ channel.region || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {{ channel.currency }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="source in channel.inventory_sources.slice(0, 3)"
                                                    :key="source.id"
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                    :class="source.pivot.is_primary ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'"
                                                >
                                                    {{ source.name }}
                                                    <span v-if="source.pivot.is_primary" class="ml-1">(Primary)</span>
                                                </span>
                                                <span
                                                    v-if="channel.inventory_sources.length > 3"
                                                    class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                                >
                                                    +{{ channel.inventory_sources.length - 3 }} more
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="channel.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                            >
                                                {{ channel.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <Link :href="route('channels.edit', channel.id)" class="text-indigo-600 hover:text-indigo-900">Edit</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="channels.data.length === 0">
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            No channels found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="channels.links && channels.links.length > 3" class="mt-6">
                            <div class="flex justify-between text-sm text-gray-500">
                                <span>
                                    Showing {{ channels.from }} to {{ channels.to }} of {{ channels.total }} results
                                </span>
                                <div class="flex gap-2">
                                    <Link
                                        v-for="link in channels.links"
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
