<?php
use Flux\Flux;
use Livewire\Component;
use App\Models\MenuItem;
use App\Models\Category;
use Livewire\WithPagination; // trait that makes pagination work with Livewire requests

new class extends Component
{
    // Must use the trait so Livewire knows how to track the current page
    use WithPagination;

    // UI-only state for now (save/update/delete logic comes later)
    public string $searchMenu = '';
    public bool $showModal = false;
    public bool $showDeleteConfirmModal = false;
    public ?int $selectedMenuId = null;
    public string $selectedMenuName = '';
    public $category_id;
    public $categories = [];
    public $filterByCategory;

    // Form fields (UI placeholders for later CRUD)
    public string $name = '';
    public string $description = '';
    public string $price = '';

    public bool $is_available = true;

    public function rules(){
        return [
            'name' =>'required|string|min:3|max:30',
            'category_id' => 'required|integer',
            'description' => 'nullable|string',
            'price' =>'required|numeric|min:1|max:800'
        ];
    }
     public function messages(){
        return [
            'name.required' =>'Menu Item is required',
            'name.min' =>'Menu Item must be atleast 3 characters',
            'name.unique' =>'Menu Item already exists',
            'price.required' =>'Price cannot be less than 3 and more than 800',
            'price.min' =>'Price cannot be less than 3 and more than 800',
            'price.max' =>'Price cannot be less than 3 and more than 800',
            'category_id.required' => 'Category is required'
        ];
    }

    public function mount(){
        //select * from categories where is_active = true order by name
        $this->categories= Category::where('is_active',1)->orderBy('name')->get();
    }
    /**
     * IMPORTANT — why pagination was broken before:
     *
     * Do NOT store the paginator in a public property inside mount():
     *   public $menus;
     *   $this->menus = MenuItem::paginate(10); // breaks when you click page 2
     *
     * Reason: Livewire re-runs on every click. The page number only updates if
     * you call paginate() AGAIN on each request. mount() runs only once, so the
     * list stays stuck on page 1.
     *
     * Correct pattern: return the paginator from with() (or render()).
     * Livewire re-evaluates with() on every request, so WithPagination works.
     */
    public function with()
    {
        $query = MenuItem::query()
                ->with('category')
                    //when user is searching for the menu ie. searchMenu is not empty then this line below is executed
                    ->when($this->searchMenu !== '', function ($query) {
                        //where name like %searchmenu%
                        $query->where('name', 'like', '%'.$this->searchMenu.'%');
                    });
        // if the user selected the filter by category
        if($this->filterByCategory){
            $query = $query->where('category_id','=',$this->filterByCategory);
        }
        $menu = $query->latest()->paginate(10); //order by date created/inserted --> orderBy('created_at','desc')
        return [
            // with('category') avoids N+1 queries when showing category name
            'menus' => $menu
        ];
    }

    // Reset page to 1 whenever the user types a new search term
    public function updatedSearchMenu(): void
    {
        $this->resetPage();
    }

    // UI stubs — implement real logic later
    public function create(): void
    {
        $this->selectedMenuId = null;
        $this->name = '';
        $this->description = '';
        $this->price = '';
        $this->category_id = null;
        $this->is_available = true;
        $this->showModal = true;
    }

    public function show(int $id): void
    {
        $this->selectedMenuId = $id;
        
        $menu = MenuItem::find($id);
        if ($menu) {
            $this->name = $menu->name;
            $this->description = (string) $menu->description;
            $this->price = (string) $menu->price;
            $this->category_id = $menu->category_id;
            $this->is_available = (bool) $menu->is_available;
            $this->showModal = true;
        }
    }

    public function showDeleteModal(int $id): void
    {
        $this->selectedMenuId = $id;
        $menu = MenuItem::find($id);
        $this->selectedMenuName = $menu?->name ?? '';
        $this->showDeleteConfirmModal = true;
    }

    public function save(): void
    {
        $this->validate();
        $this->showModal = false;
        MenuItem::create([
            'name' =>$this->name,
            'category_id' => $this->category_id,
            'description' => $this->description,
            'price' => $this->price
        ]);
    }

    public function update(): void
    {
        // TODO: validate + update
        $this->showModal = false;
        $this->validate();
        $menuItem = MenuItem::find($this->selectedMenuId);
        $menuItem->name = $this->name;
        $menuItem->price = $this->price;
        $menuItem->category_id = $this->category_id;
        $menuItem->description = $this->description;
        $menuItem->save();

        // MenuItem::find($this->selectedMenuId)->update([
        //     'name' =>$this->name,
        //     'category_id' => $this->category_id,
        //     'description' => $this->description,
        //     'price' => $this->price
        // ])
    }

    public function deleteMenu(): void
    {
        // TODO: soft delete
        MenuItem::find($this->selectedMenuId)->delete();
        Flux::toast('Your changes have been saved.');
        $this->showDeleteConfirmModal = false;
        $this->selectedMenuId = null;
        $this->selectedMenuName = '';
      
    }
};
?>

<div class="min-h-screen bg-purple-50 py-10">

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-10">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="sm:text-lg text-indigo-700 md:text-3xl font-bold tracking-tight border-l-16 border-indigo-500 rounded-lg shadow-lg bg-white p-2">
                        Menu Item Management
                    </h1>
                    <p class="mt-2 text-indigo-600">
                        Manage and organize your cafe menu items with ease.
                    </p>
                </div>

                <div class="flex items-center gap-1 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700">
                    <span class="inline-flex items-center pl-3 py-1">
                        {{ $menus->total() }}
                    </span>
                    <span class="hidden md:inline ml-1 pr-3">Menu Items</span>
                </div>
            </div>
        </div>

        <div class="flex items-center mb-2 gap-4">
            {{--
                wire:model.live = two-way binding on every keystroke
                .debounce.600ms = wait until user stops typing before searching
            --}}
            <flux:input
                wire:model.live.debounce.600ms="searchMenu"
                icon="magnifying-glass"
                placeholder="Search menu items"
                class="w-64 border rounded-md"
            />
            <flux:select wire:model.live="filterByCategory">
                <flux:select.option value="">Filter by category</flux:select.option>
                 @foreach($categories as $category)
                        <flux:select.option  value="{{$category->id}}">{{$category->name}}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button
                wire:click="create"
                variant="primary"
                class="bg-purple-700 text-white hover:bg-indigo-800"
                icon="plus"
            >
                New Menu Item
            </flux:button>
        </div>

        {{-- Main Card --}}
        <div class="bg-white rounded-2xl shadow-md border border-r-red-200 overflow-hidden">

            {{-- Table Header --}}
            <div class="px-6 py-4 border-b border-slate-100 bg-indigo-500">
                <div class="grid grid-cols-12 gap-4 text-xs font-semibold uppercase tracking-wider text-white">
                    <div class="col-span-1">#</div>
                    <div class="col-span-3">Name</div>
                    <div class="col-span-2">Category</div>
                    <div class="col-span-2 text-right">Price</div>
                    <div class="col-span-2 text-right">Status</div>
                    <div class="col-span-2 text-center">Actions</div>
                </div>
            </div>

            {{-- Menu Items List --}}
            <div class="divide-y divide-slate-100">
                {{-- @forelse = @foreach + @empty fallback when the list is empty --}}
                @forelse ($menus as $menu)
                    {{--
                        wire:key is REQUIRED in Livewire loops so rows update correctly.
                        firstItem() + iteration = correct serial number across pages
                        (page 2 starts at 11, not 1).
                    --}}
                    <div
                        wire:key="menu-{{ $menu->id }}"
                        x-data="{ hovered: false }"
                        @mouseenter="hovered = true"
                        @mouseleave="hovered = false"
                        class="grid grid-cols-12 gap-4 px-6 py-4 items-center transition-all duration-200"
                        :class="hovered ? 'bg-slate-50' : 'bg-white'"
                    >
                        {{-- Index (works correctly with pagination) --}}
                        <div class="col-span-1">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 text-slate-600 text-sm font-medium">
                                {{ $menus->firstItem() + $loop->index }}
                            </span>
                        </div>

                        {{-- Name --}}
                        <div class="col-span-3">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                <div>
                                    <span class="font-medium text-slate-800 block">
                                        {{ $menu->name }}
                                    </span>
                                    @if ($menu->description)
                                        <span class="text-xs text-slate-400 line-clamp-1">
                                            {{ $menu->description }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Category --}}
                        <div class="col-span-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium bg-slate-100 text-slate-700">
                                {{ $menu->category?->name ?? '—' }}
                            </span>
                        </div>

                        {{-- Price --}}
                        <div class="col-span-2 text-right">
                            <span class="font-semibold text-slate-800">
                                ₹{{ number_format((float) $menu->price, 2) }}
                            </span>
                        </div>

                        {{-- Status --}}
                        <div class="col-span-2 text-right">
                            <span @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => $menu->is_available,
                                'bg-red-100 text-red-800' => ! $menu->is_available,
                            ])>
                                <span @class([
                                    'w-1.5 h-1.5 rounded-full',
                                    'bg-emerald-500' => $menu->is_available,
                                    'bg-red-400' => ! $menu->is_available,
                                ])></span>
                                {{ $menu->is_available ? 'Available' : 'Unavailable' }}
                            </span>
                        </div>

                        {{-- Actions (UI only for now) --}}
                        <div class="col-span-2 flex justify-center gap-3">
                            <flux:icon.pencil-square color="green" wire:click="show({{ $menu->id }})" class="cursor-pointer" />
                            <flux:icon.trash color="red" wire:click="showDeleteModal({{ $menu->id }})" class="cursor-pointer" />
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
                        <h3 class="text-lg font-medium text-slate-900 mb-4">No menu items found</h3>
                        <p class="text-slate-500">Get started by creating your first menu item.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Pagination (Flux reads the Laravel paginator object) --}}
        <div class="mt-6">
            <flux:pagination :paginator="$menus" />
        </div>

        {{-- Footer Note --}}
        <div class="mt-4 text-center text-sm text-slate-400">
            Showing
            <span class="font-medium text-slate-500">{{ $menus->firstItem() ?? 0 }}</span>
            –
            <span class="font-medium text-slate-500">{{ $menus->lastItem() ?? 0 }}</span>
            of
            <span class="font-medium text-slate-500">{{ $menus->total() }}</span>
            menu items
        </div>
    </div>

    {{-- Modal form (create / edit — logic later) --}}
    <flux:modal wire:model.self="showModal" name="menu-item-form" class="md:w-96 bg-white">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $selectedMenuId ? 'Edit Menu Item' : 'New Menu Item' }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $selectedMenuId ? 'Update this menu item.' : 'Create a new item for your cafe menu.' }}
                </flux:text>
            </div>

            <form wire:submit="{{ $selectedMenuId ? 'update' : 'save' }}" class="space-y-4">
                <flux:select 
                    searchable 
                    wire:model="category_id" 
                    placeholder="Choose Category...">
                    <flux:select.option value="">Select Category</flux:select.option>
                    @foreach($categories as $category)
                        <flux:select.option  value="{{$category->id}}">{{$category->name}}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" label="Name" placeholder="Menu item name" />
                <flux:input wire:model="description" label="Description" placeholder="Short description" />
                <flux:input wire:model="price" type="number" step="0.01" min="0" label="Price (₹)" placeholder="0.00" />

                <div class="flex items-center justify-between mt-4">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">
                        {{ $selectedMenuId ? 'Update' : 'Save' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model.self="showDeleteConfirmModal" name="delete-menu-item" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete menu item?</flux:heading>
                <flux:text class="mt-2">
                    You're about to delete {{ $selectedMenuName }}.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="deleteMenu" variant="danger">Yes</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
