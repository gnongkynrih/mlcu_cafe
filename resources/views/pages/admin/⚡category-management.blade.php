<?php

use Livewire\Component;
use App\Models\Category;

new class extends Component
{
    public $categories= []; //to store the categories

    //mount is a lifecycle method that is called when the component is loaded
    public function mount()
    {
        //select * from categories
        // $this->categories = Category::all();

        //select * from categories sort by sort_order ascending
        $this->categories = Category::orderBy('sort_order', 'asc')
            ->get();
    }
    
}
?>

<div class="min-h-screen bg-purple-50 py-10">

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-10">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="sm:text-lg text-indigo-700 md:text-3xl font-bold  tracking-tight border-l-16 border-indigo-500 rounded-lg shadow-lg bg-white p-2">
                        Category Management
                    </h1>
                    <p class="mt-2 text-indigo-600">
                        Manage and organize your categories with ease.
                    </p>
                </div>

               
                <div class="flex items-center gap-1 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700">
                    <span class="inline-flex items-center pl-3 py-1 ">
                        {{ count($categories) }} 
                    </span>
                    <span class="hidden md:inline ml-1 pr-3">Categories</span>
                </div>
            </div>
        </div>

       <div class="flex justify-end mb-2">
        <flux:modal.trigger name="add-category"> 
            <flux:button variant="primary"class="bg-purple-700 text-white hover:bg-indigo-800" icon="plus">New Category</flux:button>
        </flux:modal.trigger>
        </div>
        {{-- Main Card --}}
        <div class="bg-white rounded-2xl shadow-md border border-r-red-200 overflow-hidden">
            
            {{-- Table Header --}}
            <div class="px-6 py-4 border-b border-slate-100 bg-indigo-500">
                <div class="grid grid-cols-12 gap-4 text-xs font-semibold  uppercase tracking-wider">
                    <div class="col-span-1">#</div>
                    <div class="col-span-7">Category Name</div>
                    <div class="col-span-2 text-center">Sort Order</div>
                    <div class="col-span-2 text-right">Status</div>
                </div>
            </div>

            {{-- Categories List --}}
            <div class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <div 
                        x-data="{ hovered: false }"
                        @mouseenter="hovered = true"
                        @mouseleave="hovered = false"
                        class="grid grid-cols-12 gap-4 px-6 py-4 items-center transition-all duration-200"
                        :class="hovered ? 'bg-slate-50' : 'bg-white'"
                    >
                        {{-- Index --}}
                        <div class="col-span-1">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 text-slate-600 text-sm font-medium">
                                {{ $loop->iteration }}
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
                        @php
                            $status = $category->is_active;
                            $statusClass = $category->is_active ? 'bg-emerald-100 text-slate-800' : 'bg-red-100 text-red-800';
                            $statusText = $category->is_active ? 'Active' : 'Inactive';
                            $statusDotClass = $category->is_active ? 'bg-emerald-500' : 'bg-red-300';
                        @endphp
                        {{-- Status Badge --}}
                        
                        <div class="col-span-2 text-right">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statusDotClass }}">

                                </span>
                                 {{ $statusText }} 
                            </span>
                        </div>
                    </div>
                @empty
                    {{-- Empty State --}}
                    <div class="px-6 py-16 text-center bg-red-100">
                        <div class="mx-auto w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-slate-900 mb-4">No categories found</h3>
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

    <!-- Modal form -->
    <flux:modal name="add-category" class="md:w-96 bg-white ">
        <div class="space-y-6 ">
            <div>
                <flux:heading size="lg">New Categories</flux:heading>
                <flux:text class="mt-2">Create a new category for your menu.</flux:text>
            </div>
            <flux:input label="Name" placeholder="Category name" />
            <flux:input label="Description" placeholder="Category description" />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>
</div>