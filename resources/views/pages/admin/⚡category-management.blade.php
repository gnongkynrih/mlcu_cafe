<?php

// Livewire lets us build interactive pages using only PHP — no JavaScript needed.
// This file is a "single-file component": the PHP class AND the HTML view live together.
use Livewire\Component;
use App\Models\Category;
use Flux\Flux;          // Flux = UI component library (modals, inputs, toasts, etc.)
use App\Services\CategoryService; // Our own helper class for database queries

// Livewire components are classes that extend Livewire\Component
new class extends Component
{
    // ---------------------------------------------------------------------
    // PUBLIC PROPERTIES — the "state" of this component.
    //
    // IMPORTANT CONCEPT: Livewire serializes (saves) every public property to
    // JSON between requests, then restores them on the next request.
    // This is called "dehydration" / "hydration" — it is how Livewire
    // remembers data without a full page reload.
    // Because of this, public properties can only hold simple types:
    // strings, numbers, booleans, arrays, Collections and Models.
    // ---------------------------------------------------------------------
    public $categories= [];           // the list of categories shown in the table
    public $name;                     // bound to the "Name" input in the modal
    public $description;              // bound to the "Description" input
    public $showModal;                // true = open the add/edit modal
    public $showDeleteConfirmModal;   // true = open the delete confirmation modal
    public $selectedCategoryId;       // id of the category being edited/deleted
    public $searchCategory;           // bound to the search box
    public $selectedCategoryName;     // name shown inside the delete modal

    // ---------------------------------------------------------------------
    // WHY IS THIS A protected METHOD and NOT a public property?
    //
    // Services (plain PHP classes) cannot be serialized by Livewire.
    // If you do `public $categoryService;` Livewire will crash with:
    //   "Property type not supported in Livewire"
    //
    // Instead, we create it fresh each time we need it via app(),
    // which asks Laravel's service container to give us the class.
    // app(CategoryService::class)  ===  new CategoryService()  (but managed)
    // ---------------------------------------------------------------------
    protected function categoryService(): CategoryService
    {
        return app(CategoryService::class);
    }

    // Small helper that clears the form and closes all modals.
    // We call it after save / update / delete so the UI resets.
    public function resetFrom(){
        $this->name='';
        $this->description='';
        $this->showModal = false;
        $this->showDeleteConfirmModal=false;
        $this->selectedCategoryId = null;
        $this->searchCategory='';
        $this->selectedCategoryName='';
    }

    // ---------------------------------------------------------------------
    // VALIDATION RULES — same rules you already know from Laravel requests.
    // The keys ('name', 'description') must match public property names.
    // 'unique:categories,name' means: the name must not already exist
    // in the `name` column of the `categories` table.
    // ---------------------------------------------------------------------
    public function rules(){
        return [
            'name' => 'required|string|max:25|min:3|unique:categories,name',
            'description' => 'nullable|string|max:255',
        ];
    }

    // Custom messages: shown instead of Laravel's default error text.
    // Format: 'property.rule' => 'Your message'
    public function messages(){
        return [
            'name.required' =>'Category Name is required',
            'name.min' =>'Category Name must be atleast 3 characters',
            'name.unique' =>'Category Name already exists',
        ];
    }

    // ---------------------------------------------------------------------
    // mount() — runs ONCE when the page/component first loads.
    // Think of it like a constructor: use it to load initial data.
    // Equivalent SQL: SELECT * FROM categories ORDER BY sort_order ASC
    // ---------------------------------------------------------------------
    public function mount()
    {
        $this->showModal = false;
        $this->showDeleteConfirmModal = false;
        //select * from categories sort by sort_order ascending
        $this->categories = $this->categoryService()->getAllCategories('sort_order');
    }

    // ---------------------------------------------------------------------
    // LIFECYCLE HOOK: "updated{PropertyName}()"
    // Livewire calls this automatically EVERY TIME the $searchCategory
    // property changes (because the input uses wire:model.live).
    // So as the user types, this re-runs the search query.
    // SQL equivalent: SELECT * FROM categories
    //                 WHERE name LIKE '%keyword%'
    //                 ORDER BY sort_order ASC
    // ---------------------------------------------------------------------
    public function updatedSearchCategory(){
        $this->categories = Category::where('name', 'like', '%'.$this->searchCategory.'%')
            ->orderBy('sort_order', 'asc')
            ->get();
    }
    
    
    // Opens the modal in "create" mode.
    // Clearing selectedCategoryId is how the form knows it should SAVE
    // (insert new) instead of UPDATE — see the modal's wire:submit below.
    public function create(){

        $this->selectedCategoryId = null;
        $this->name = '';
        $this->description = '';
        $this->showModal = true;
    }

    // Called when the modal form is submitted while creating.
    public function save(){

        //validate the input — checks rules() above; on failure Livewire
        //shows the error next to the input automatically
        $this->validate();

        //insert into categories () values ()
        Category::create([
            'name' => $this->name,
            'description' => $this->description,
        ]);
        
        //reset the form
        $this->resetFrom();
        
        //refresh the categories so the new row appears in the table
        $this->categories = $this->categoryService()->getAllCategories('sort_order');

        // Flux::toast shows a small popup notification in the corner
        Flux::toast(
            duration:3000,
            heading:'Save',
            text:'Category saved successfully',
            position:'top end'
        );
    }


    // Opens the modal in "edit" mode: load one category's data
    // into the form fields so the user can modify it.
    public function show($id){
        $this->selectedCategoryId = $id;
        //select * from categories where id = $id
        $category = Category::find($id); //find searches by id
        $this->name = $category->name;
        $this->description = $category->description;
        $this->showModal = true;
    }

    // Called when the modal form is submitted while editing.
    public function update(){
        //selecting the category base on id and then update the data
        Category::where('id', $this->selectedCategoryId)->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);
        
        //refresh the categories
        $this->categories = $this->categoryService()->getAllCategories('sort_order');
        
        $this->resetFrom();
    }

    // Toggles the Active/Inactive switch.
    // findOrFail - if the id does not exist, Laravel shows a 404 page
    public function updateStatus($id): void
    {
        $category = Category::findOrFail($id);
        $category->is_active = ! $category->is_active; // flip true<->false
        $category->save();

        $this->categories = $this->categoryService()->getAllCategories('sort_order');
    }

    // Saves a new sort order when the user finishes editing the number
    // input (triggered by wire:blur = when the input loses focus).
    public function updateSortOrder($id, $sortOrder): void
    {
        // Only accept whole numbers 0 and up
        if (! is_numeric($sortOrder) || (int) $sortOrder < 0) {
            return;
        }

        $category = Category::findOrFail($id);
        $category->sort_order = (int) $sortOrder;
        $category->save();
        Flux::toast(
            heading: 'Sort order updated successfully.',
            text: 'The category has been reordered.',
        );

        $this->categories = $this->categoryService()->getAllCategories('sort_order');
    }

    // Opens the delete confirmation modal and remembers WHICH category.
    public function showDeleteModal($id){
        $this->selectedCategoryId = $id;
        //search for the categroy
        $cat = Category::findOrFail($id);
        $this->selectedCategoryName = $cat->name;
        $this->showDeleteConfirmModal = true;
    }

    // Actually deletes the row after the user clicks "Yes".
    // NOTE: Category model uses SoftDeletes — the row is only
    // "marked" deleted (deleted_at column set), not really removed.
    public function deleteCategory(){
        Category::findOrFail($this->selectedCategoryId)->delete();
        Flux::toast($this->selectedCategoryName .' successfully deleted');
        $this->categories = $this->categoryService()->getAllCategories('sort_order');
        $this->resetFrom();
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

       <div class="flex justify-end mb-2 gap-4">
            {{--
                wire:model.live   = two-way binding to $searchCategory, sent on every keystroke
                .debounce.600ms   = wait 600ms after the user STOPS typing before sending
                                    the request (saves server calls while searching)
            --}}
            <flux:input 
                wire:model.live.debounce.600ms="searchCategory"
                icon="magnifying-glass" 
                placeholder="Search categories" 
                class="w-64 border rounded-md"
            />
            {{-- wire:click="create" calls the create() method in the class above --}}
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
                {{-- @forelse = @foreach + @empty fallback when the list is empty --}}
                @forelse ($categories as $index => $category)
                    {{--
                        wire:key = REQUIRED in Livewire loops. It gives each row a
                        unique id so Livewire can correctly update/remove rows
                        without mixing them up after re-rendering.
                        x-data / @mouseenter = Alpine.js (included with Livewire) —
                        pure client-side behaviour, no server request needed.
                    --}}
                    <div
                        wire:key="category-{{ $category->id }}"
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
                        <div class="col-span-2 flex justify-center">
                            {{--
                                wire:blur = call updateSortOrder() when the user clicks
                                away / tabs out of this input.
                                $event.target.value = the current text inside the input,
                                sent as the 2nd argument to the method.
                                Note: we do NOT use wire:model here because the value
                                is already stored in the database — we just save on blur.
                            --}}
                            <flux:input
                                type="number"
                                min="0"
                                size="sm"
                                value="{{ $category->sort_order }}"
                                wire:blur="updateSortOrder({{ $category->id }}, $event.target.value)"
                                class="w-16 border"
                                input:class="text-center"
                            />
                        </div>
                        {{-- Status Badge --}}
                        
                        <div class="col-span-2 text-right">
                            {{-- flux:field variant="inline" puts the label and the
                                 control on one line instead of stacked vertically --}}
                            <flux:field variant="inline">
                                {{--
                                    @class = Blade helper for conditional CSS classes:
                                    'class-name' => condition  (class applies when true)
                                    The "!" at the end = Tailwind "important", needed
                                    to override Flux's built-in colors.
                                --}}
                                <flux:label @class([
                                    'text-emerald-600!' => $category->is_active,
                                    'text-red-600!' => ! $category->is_active,
                                ])>
                                    {{-- Ternary operator: condition ? valueIfTrue : valueIfFalse --}}
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </flux:label>

                                {{--
                                    :checked="..." = PHP expression (dynamic attribute)
                                    data-checked:bg-* = CSS class applied only when the
                                    switch has the data-checked attribute (on state)
                                --}}
                                <flux:switch
                                    wire:click="updateStatus({{ $category->id }})"
                                    :checked="$category->is_active"
                                    class="bg-red-400! data-checked:bg-emerald-500! data-checked:border-emerald-500!"
                                />

                                <flux:error name="is_active" />
                            </flux:field>
                        </div>
                        <div class="col-span-1 flex">
                            {{-- Passing the row's id to the method: wire:click="show(3)" --}}
                            <flux:icon.pencil-square color="green" wire:click="show({{ $category->id }})" />
                            
                            <flux:icon.trash color="red" wire:click="showDeleteModal({{ $category->id }})" />
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

    <!-- Modal form (used for BOTH create and edit) -->
    {{--
        wire:model.self = binds the modal open/close state to $showModal
        Setting $showModal = true in PHP opens it; clicking outside closes it.
    --}}
    <flux:modal wire:model.self="showModal" name="add-category" class="md:w-96 bg-white ">
        <div class="space-y-6 ">
            <div>
                <flux:heading size="lg">New Categories</flux:heading>
                <flux:text class="mt-2">Create a new category for your menu.</flux:text>
            </div>
            {{--
                ONE form handles create + edit:
                - no category selected  -> wire:submit="save"   (insert)
                - a category selected   -> wire:submit="update" (update row)
                wire:submit works like a normal form submit but calls a Livewire
                method instead of reloading the page.
            --}}
            <form wire:submit="{{ $selectedCategoryId ? 'update' : 'save' }}">
                {{-- wire:model (no .live) = value is sent only when form submits --}}
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

    <!-- //modal to delete — asks for confirmation before deleting -->
    <flux:modal wire:model.self="showDeleteConfirmModal" name="delete-category" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete category?</flux:heading>
                <flux:text class="mt-2">
                    You're about to delete {{ $selectedCategoryName}}.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="deleteCategory" variant="danger">Yes</flux:button>
            </div>
        </div>
    </flux:modal>
</div>