<?php

use Livewire\Component;
use App\Models\Category;

new class extends Component
{
    public $categories= []; //to store the categories
    public function mount()
    {
        //select * from categories
        // $this->categories = Category::all();
        //select * from categories sort by sort_order ascending
        $this->categories = Category::orderBy('sort_order', 'asc')->get();
    }
}
?>

<div class="min-h-screen bg-slate-50 py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-10">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">
                        Category Management
                    </h1>
                    <p class="mt-2 text-slate-500">
                        Manage and organize your categories with ease.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700">
                        {{ count($categories) }} Categories
                    </span>
                </div>
            </div>
        </div>

        {{-- Main Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            
            {{-- Table Header --}}
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <div class="grid grid-cols-12 gap-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    <div class="col-span-1">#</div>
                    <div class="col-span-7">Category Name</div>
                    <div class="col-span-2 text-center">Sort Order</div>
                    <div class="col-span-2 text-right">Status</div>
                </div>
            </div>

            {{-- Categories List --}}
            <div class="divide-y divide-slate-100">
                @forelse ($categories as $index => $category)
                    <div 
                        x-data="{ hovered: false }"
                        @mouseenter="hovered = true"
                        @mouseleave="hovered = false"
                        class="grid grid-cols-12 gap-4 px-6 py-4 items-center transition-all duration-200"
                        :class="hovered ? 'bg-slate-50' : 'bg-white'"
                    >
                        {{-- Index --}}
                        <div class="col-span-1">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium">
                                {{ $index + 1 }}
                            </span>
                        </div>

                        {{-- Name --}}
                        <div class="col-span-7">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                <span class="font-medium text-slate-800">
                                    {{ $category->name }}
                                </span>
                            </div>
                        </div>

                        {{-- Sort Order --}}
                        <div class="col-span-2 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium bg-slate-100 text-slate-700">
                                {{ $category->sort_order }}
                            </span>
                        </div>

                        {{-- Status Badge --}}
                        <div class="col-span-2 text-right">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        </div>
                    </div>
                @empty
                    {{-- Empty State --}}
                    <div class="px-6 py-16 text-center">
                        <div class="mx-auto w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-slate-900 mb-1">No categories found</h3>
                        <p class="text-slate-500">Get started by creating your first category.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Footer Note --}}
        <div class="mt-6 text-center text-sm text-slate-400">
            Categories are sorted by <span class="font-medium text-slate-500">sort order</span> ascending
        </div>
    </div>
</div>