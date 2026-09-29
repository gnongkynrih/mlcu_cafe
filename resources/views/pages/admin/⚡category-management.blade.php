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

<div class="mx-auto max-w-5xl space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">Category Management</flux:heading>
                <flux:badge color="amber" size="sm">{{ count($categories) }} {{ Str::plural('category', count($categories)) }}</flux:badge>
            </div>
            <flux:text class="mt-1">Manage and organize your menu categories.</flux:text>
        </div>

        {{-- wire:click="create" calls the create() method in the class above --}}
        <flux:button wire:click="create" variant="primary" icon="plus">New Category</flux:button>
    </div>

    {{-- Toolbar --}}
    <div class="flex">
        {{--
            wire:model.live   = two-way binding to $searchCategory, sent on every keystroke
            .debounce.600ms   = wait 600ms after the user STOPS typing before sending
                                the request (saves server calls while searching)
        --}}
        <flux:input
            wire:model.live.debounce.600ms="searchCategory"
            icon="magnifying-glass"
            placeholder="Search categories..."
            clearable
            class="w-full sm:max-w-xs"
        />
    </div>

    {{-- Main Card --}}
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <div class="min-w-[640px]">

                {{-- Table Header --}}
                <div class="grid grid-cols-12 gap-4 border-b border-stone-200 bg-stone-50 px-6 py-3 text-xs font-semibold uppercase tracking-wider text-stone-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                    <div class="col-span-1">#</div>
                    <div class="col-span-5">Category Name</div>
                    <div class="col-span-2 text-center">Sort Order</div>
                    <div class="col-span-2 text-center">Status</div>
                    <div class="col-span-2 text-right">Actions</div>
                </div>

                {{-- Categories List --}}
                <div class="divide-y divide-stone-100 dark:divide-zinc-800">
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
                            class="grid grid-cols-12 items-center gap-4 px-6 py-3 transition-colors duration-150"
                            :class="hovered ? 'bg-amber-50/60 dark:bg-zinc-800/60' : ''"
                        >
                            {{-- Index --}}
                            <div class="col-span-1 text-sm tabular-nums text-stone-400 dark:text-zinc-500">
                                {{ $loop->iteration }}
                            </div>

                            {{-- Name --}}
                            <div class="col-span-5 min-w-0">
                                <p class="truncate font-medium text-stone-800 dark:text-zinc-100">{{ $category->name }}</p>
                                @if ($category->description)
                                    <p class="truncate text-xs text-stone-500 dark:text-zinc-400">{{ $category->description }}</p>
                                @endif
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
                                    class="w-20"
                                    input:class="text-center"
                                />
                            </div>

                            {{-- Status --}}
                            <div class="col-span-2 flex justify-center">
                                {{-- flux:field variant="inline" puts the label and the
                                     control on one line instead of stacked vertically --}}
                                <flux:field variant="inline">
                                    {{--
                                        :checked="..." = PHP expression (dynamic attribute)
                                        data-checked:bg-* = CSS class applied only when the
                                        switch has the data-checked attribute (on state)
                                    --}}
                                    <flux:switch
                                        wire:click="updateStatus({{ $category->id }})"
                                        :checked="$category->is_active"
                                        class="data-checked:bg-emerald-500! data-checked:border-emerald-500!"
                                    />

                                    {{--
                                        @class = Blade helper for conditional CSS classes:
                                        'class-name' => condition  (class applies when true)
                                        The "!" at the end = Tailwind "important", needed
                                        to override Flux's built-in colors.
                                    --}}
                                    <flux:label @class([
                                        'w-14 text-xs!',
                                        'text-emerald-600! dark:text-emerald-400!' => $category->is_active,
                                        'text-stone-400! dark:text-zinc-500!' => ! $category->is_active,
                                    ])>
                                        {{-- Ternary operator: condition ? valueIfTrue : valueIfFalse --}}
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </flux:label>

                                    <flux:error name="is_active" />
                                </flux:field>
                            </div>

                            {{-- Actions --}}
                            <div class="col-span-2 flex justify-end gap-1">
                                {{-- Passing the row's id to the method: wire:click="show(3)" --}}
                                <flux:tooltip content="Edit">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="show({{ $category->id }})" />
                                </flux:tooltip>
                                <flux:tooltip content="Delete">
                                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-500! hover:bg-red-50! dark:hover:bg-red-500/10!" wire:click="showDeleteModal({{ $category->id }})" />
                                </flux:tooltip>
                            </div>
                        </div>
                    @empty
                        {{-- Empty State --}}
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-500/10">
                                <flux:icon.tag class="size-7 text-amber-500" />
                            </div>
                            <flux:heading size="lg">No categories found</flux:heading>
                            <flux:text class="mt-1">Get started by creating your first category.</flux:text>
                            <flux:button wire:click="create" variant="primary" icon="plus" size="sm" class="mt-4">New Category</flux:button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Footer Note --}}
    <flux:text class="text-center text-sm">
        Categories are sorted by <span class="font-medium">sort order</span> ascending. Change a number and click away to save it.
    </flux:text>

    <!-- Modal form (used for BOTH create and edit) -->
    {{--
        wire:model.self = binds the modal open/close state to $showModal
        Setting $showModal = true in PHP opens it; clicking outside closes it.
    --}}
    <flux:modal wire:model.self="showModal" name="add-category" class="w-full md:w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $selectedCategoryId ? 'Edit Category' : 'New Category' }}</flux:heading>
                <flux:text class="mt-2">{{ $selectedCategoryId ? 'Update the details of this category.' : 'Create a new category for your menu.' }}</flux:text>
            </div>
            {{--
                ONE form handles create + edit:
                - no category selected  -> wire:submit="save"   (insert)
                - a category selected   -> wire:submit="update" (update row)
                wire:submit works like a normal form submit but calls a Livewire
                method instead of reloading the page.
            --}}
            <form wire:submit="{{ $selectedCategoryId ? 'update' : 'save' }}" class="space-y-4">
                {{-- wire:model (no .live) = value is sent only when form submits --}}
                <flux:input wire:model="name" label="Name" placeholder="e.g. Hot Beverages" />
                <flux:textarea wire:model="description" label="Description" placeholder="Short description (optional)" rows="3" />
                <div class="flex justify-end gap-2 pt-2">
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
                    You're about to delete <strong>{{ $selectedCategoryName }}</strong>.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="deleteCategory" variant="danger">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
