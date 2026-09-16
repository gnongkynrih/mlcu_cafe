<?php

use Livewire\Component;
use App\Models\Category;

new class extends Component
{
    public $categories= []; //to store the categories
    public $name;
    public $description;
    public $showModal;
    public $selectedCategoryId;

    public function rules(){
        return [
            'name' => 'required|string|max:25|min:3|unique:categories,name',
            'description' => 'nullable|string|max:255',
        ];
    }
    public function messages(){
        return [
            'name.required' =>'Category Name is required',
            'name.min' =>'Category Name must be atleast 3 characters',
            'name.unique' =>'Category Name already exists',
        ];
    }
    //mount is a lifecycle method that is called when the component is loaded
    public function mount()
    {
        $this->showModal = false;
        //select * from categories
        // $this->categories = Category::all();

        //select * from categories sort by sort_order ascending
        $this->categories = Category::orderBy('sort_order', 'asc')
            ->get();
    }
    
    public function create(){
        $this->selectedCategoryId = null;
        $this->name = '';
        $this->description = '';
        $this->showModal = true;
    }
    public function save(){

        //validate the input
        $this->validate();

        //insert into categories () values ()
        Category::create([
            'name' => $this->name,
            'description' => $this->description,
        ]);
        
        //reset the form
        $this->name = '';
        $this->description = '';
        
        //refresh the categories
        $this->categories = Category::orderBy('sort_order', 'asc')->get();
    }


    //pass the id of the category
    public function show($id){
        $this->selectedCategoryId = $id;
        //select * from categories where id = $id
        $category = Category::find($id); //find searches by id
        $this->name = $category->name;
        $this->description = $category->description;
        $this->showModal = true;
    }

    public function update(){
        //selecting the category base on id and then update the data
        Category::where('id', $this->selectedCategoryId)->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);
        
        //refresh the categories
        $this->categories = Category::orderBy('sort_order', 'asc')->get();
        
        //close the modal
        $this->showModal = false;
        $this->selectedCategoryId = null;
    }
    public function updateStatus($id): void
    {
        //findOrFail - if the id does not exist show 404 page
        $category = Category::findOrFail($id);
        $category->is_active = ! $category->is_active;
        $category->save();

        $this->categories = Category::orderBy('sort_order', 'asc')->get();
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
       
            <flux:button wire:click="create" variant="primary"class="bg-purple-700 text-white hover:bg-indigo-800" icon="plus">New Category</flux:button>
        </div>
        {{-- Main Card --}}
        <div class="bg-white rounded-2xl shadow-md border border-r-red-200 overflow-hidden">
            
            {{-- Table Header --}}
            <div class="px-6 py-4 border-b border-slate-100 bg-indigo-500">
                <div class="grid grid-cols-12 gap-4 text-xs font-semibold  uppercase tracking-wider">
                    <div class="col-span-1">#</div>
                    <div class="col-span-6">Category Name</div>
                    <div class="col-span-2 text-center">Sort Order</div>
                    <div class="col-span-2 text-right">Status</div>
                    <div class="col-span-1 text-center">Actions</div>
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
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 text-slate-600 text-sm font-medium">
                                {{ $loop->iteration }}
                            </span>
                        </div>

                        {{-- Name --}}
                        <div class="col-span-6">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                <span class="font-medium text-slate-800">
                                    {{ $category->name }}
                                </span>
                            </div>
                        </div>

                        {{-- Sort Order --}}
                        <div class="col-span-2 text-center">
                            <flux:input type="number" wire:model.blur="category.{{ $index }}.sort_order" />
                        </div>
                        @php
                            $status = $category->is_active;
                            $statusClass = $category->is_active ? 'bg-emerald-100 text-slate-800' : 'bg-red-100 text-red-800';
                            $statusText = $category->is_active ? 'Active' : 'Inactive';
                            $statusDotClass = $category->is_active ? 'bg-emerald-500' : 'bg-red-300';
                        @endphp
                        {{-- Status Badge --}}
                        
                        <div class="col-span-2 text-right">
                            <flux:field variant="inline">
                                <flux:label @class([
                                    'text-emerald-600!' => $category->is_active,
                                    'text-red-600!' => ! $category->is_active,
                                ])>
                                    {{ $statusText }}
                                </flux:label>

                                <flux:switch
                                    wire:click="updateStatus({{ $category->id }})"
                                    :checked="$category->is_active"
                                    class="bg-red-400! data-checked:bg-emerald-500! data-checked:border-emerald-500!"
                                />

                                <flux:error name="is_active" />
                            </flux:field>
                            <!-- <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statusDotClass }}">

                                </span>
                                 {{ $statusText }} 
                            </span> -->
                        </div>
                        <div class="col-span-1 flex">
                            
                            <flux:icon.pencil-square color="green" wire:click="show({{ $category->id }})" />
                            
                            <flux:icon.trash color="red" />
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
    <flux:modal wire:model.self="showModal" name="add-category" class="md:w-96 bg-white ">
        <div class="space-y-6 ">
            <div>
                <flux:heading size="lg">New Categories</flux:heading>
                <flux:text class="mt-2">Create a new category for your menu.</flux:text>
            </div>
            <form wire:submit="{{ $selectedCategoryId ? 'update' : 'save' }}">
                <flux:input wire:model="name" label="Name" placeholder="Category name" />
                <flux:input wire:model="description" label="Description" placeholder="Category description" />
                <div class="flex items-center justify-between mt-4">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ $selectedCategoryId ? 'Update' : 'Save' }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>