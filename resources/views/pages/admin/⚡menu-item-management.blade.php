<?php
use Flux\Flux;
use Livewire\Component;
use App\Models\MenuItem;
use App\Models\Category;
use Livewire\WithPagination; // trait that makes pagination work with Livewire requests
use Livewire\WithFileUploads; // trait that lets a component receive uploaded files
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    // Must use the trait so Livewire knows how to track the current page
    use WithPagination;

    // Must use this trait so wire:model can be bound to a file input
    use WithFileUploads;

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

    // The NEW image picked in the form. While the form is open, Livewire keeps
    // the file in a temporary folder; it is only saved for real in save()/update().
    public $image;

    // Path of the image ALREADY saved for this item (used as a preview when editing)
    public ?string $existingImage = null;

    public function rules(){
        return [
            'name' =>'required|string|min:3|max:30',
            'category_id' => 'required|integer',
            'description' => 'nullable|string',
            'price' =>'required|numeric|min:1|max:800',
            // image = must be jpg, jpeg, png, bmp, gif, svg or webp
            // max:2048 = size limit in kilobytes (2 MB)
            'image' => 'nullable|image|max:2048',
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
            'category_id.required' => 'Category is required',
            'image.image' => 'The file must be an image',
            'image.max' => 'The image cannot be larger than 2 MB',
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

    // Runs as soon as a file is picked, so a wrong file type or a file
    // that is too big shows an error straight away (before clicking Save)
    public function updatedImage(): void
    {
        $this->validateOnly('image');
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
        $this->image = null;
        $this->existingImage = null;
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
            $this->image = null;
            $this->existingImage = $menu->image;
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
            'price' => $this->price,
            // store() moves the file into storage/app/public/menu-items and
            // returns its path, e.g. "menu-items/aB3x....jpg". We save that path.
            // ?-> = only call store() if an image was picked (otherwise null)
            'image' => $this->image?->store('menu-items', 'public'),
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

        // Only replace the image if the user picked a new one
        if ($this->image) {
            // delete the old file so unused images do not pile up
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $menuItem->image = $this->image->store('menu-items', 'public');
        }

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

<div class="mx-auto max-w-6xl space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">Menu Item Management</flux:heading>
                <flux:badge color="amber" size="sm">{{ $menus->total() }} {{ Str::plural('item', $menus->total()) }}</flux:badge>
            </div>
            <flux:text class="mt-1">Manage and organize your cafe menu items.</flux:text>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">New Menu Item</flux:button>
    </div>

    {{-- Toolbar: search + category filter --}}
    <div class="flex flex-col gap-3 sm:flex-row">
        {{--
            wire:model.live = two-way binding on every keystroke
            .debounce.600ms = wait until user stops typing before searching
        --}}
        <flux:input
            wire:model.live.debounce.600ms="searchMenu"
            icon="magnifying-glass"
            placeholder="Search menu items..."
            clearable
            class="sm:max-w-xs"
        />
        <flux:select wire:model.live="filterByCategory" class="sm:max-w-56">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach($categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Main Card --}}
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <div class="min-w-[720px]">

                {{-- Table Header --}}
                <div class="grid grid-cols-12 gap-4 border-b border-stone-200 bg-stone-50 px-6 py-3 text-xs font-semibold uppercase tracking-wider text-stone-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                    <div class="col-span-1">#</div>
                    <div class="col-span-4">Name</div>
                    <div class="col-span-2">Category</div>
                    <div class="col-span-2 text-right">Price</div>
                    <div class="col-span-2 text-center">Status</div>
                    <div class="col-span-1 text-right">Actions</div>
                </div>

                {{-- Menu Items List --}}
                <div class="divide-y divide-stone-100 dark:divide-zinc-800">
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
                            class="grid grid-cols-12 items-center gap-4 px-6 py-3 transition-colors duration-150"
                            :class="hovered ? 'bg-amber-50/60 dark:bg-zinc-800/60' : ''"
                        >
                            {{-- Index (works correctly with pagination) --}}
                            <div class="col-span-1 text-sm tabular-nums text-stone-400 dark:text-zinc-500">
                                {{ $menus->firstItem() + $loop->index }}
                            </div>

                            {{-- Name --}}
                            <div class="col-span-4 flex min-w-0 items-center gap-3">
                                {{-- image_url comes from the imageUrl() accessor in the MenuItem model --}}
                                @if ($menu->image_url)
                                    <img src="{{ $menu->image_url }}" alt="{{ $menu->name }}" class="size-10 shrink-0 rounded-lg object-cover">
                                @else
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-stone-400 dark:bg-zinc-800 dark:text-zinc-500">
                                        <flux:icon.photo variant="micro" />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-stone-800 dark:text-zinc-100">{{ $menu->name }}</p>
                                    @if ($menu->description)
                                        <p class="truncate text-xs text-stone-500 dark:text-zinc-400">{{ $menu->description }}</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Category --}}
                            <div class="col-span-2">
                                <flux:badge size="sm" color="zinc">{{ $menu->category?->name ?? '—' }}</flux:badge>
                            </div>

                            {{-- Price --}}
                            <div class="col-span-2 text-right font-semibold tabular-nums text-stone-800 dark:text-zinc-100">
                                ₹{{ number_format((float) $menu->price, 2) }}
                            </div>

                            {{-- Status --}}
                            <div class="col-span-2 text-center">
                                <flux:badge size="sm" :color="$menu->is_available ? 'emerald' : 'red'" inset="top bottom">
                                    {{ $menu->is_available ? 'Available' : 'Unavailable' }}
                                </flux:badge>
                            </div>

                            {{-- Actions --}}
                            <div class="col-span-1 flex justify-end gap-1">
                                <flux:tooltip content="Edit">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="show({{ $menu->id }})" />
                                </flux:tooltip>
                                <flux:tooltip content="Delete">
                                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-500! hover:bg-red-50! dark:hover:bg-red-500/10!" wire:click="showDeleteModal({{ $menu->id }})" />
                                </flux:tooltip>
                            </div>
                        </div>
                    @empty
                        {{-- Empty State --}}
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-500/10">
                                <flux:icon.book-open class="size-7 text-amber-500" />
                            </div>
                            <flux:heading size="lg">No menu items found</flux:heading>
                            <flux:text class="mt-1">Try a different search, or create your first menu item.</flux:text>
                            <flux:button wire:click="create" variant="primary" icon="plus" size="sm" class="mt-4">New Menu Item</flux:button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Pagination (Flux reads the Laravel paginator object) --}}
        <div class="flex flex-col items-center justify-between gap-3 border-t border-stone-200 px-6 py-3 sm:flex-row dark:border-zinc-800">
            <flux:text class="text-sm">
                Showing
                <span class="font-medium">{{ $menus->firstItem() ?? 0 }}</span>
                –
                <span class="font-medium">{{ $menus->lastItem() ?? 0 }}</span>
                of
                <span class="font-medium">{{ $menus->total() }}</span>
                menu items
            </flux:text>
            <flux:pagination :paginator="$menus" class="border-0! pt-0!" />
        </div>
    </div>

    {{-- Modal form (create / edit) --}}
    <flux:modal wire:model.self="showModal" name="menu-item-form" class="w-full md:w-md">
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
                <flux:select wire:model="category_id" label="Category">
                    <flux:select.option value="">Select category...</flux:select.option>
                    @foreach($categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" label="Name" placeholder="e.g. Cappuccino" />
                <flux:textarea wire:model="description" label="Description" placeholder="Short description (optional)" rows="2" />
                <flux:input wire:model="price" type="number" step="0.01" min="0" label="Price" placeholder="0.00" icon="currency-rupee" />

                {{-- Image upload --}}
                <flux:field>
                    <flux:label>Food Image</flux:label>
                    <div class="flex items-center gap-4">
                        {{--
                            Preview:
                            - a new file was picked  -> temporaryUrl() shows it before it is saved
                              (isPreviewable() = false for non-image files, so we skip those)
                            - editing, nothing picked -> show the image already saved
                        --}}
                        <div class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-stone-300 bg-stone-50 dark:border-zinc-700 dark:bg-zinc-800">
                            @if ($image && $image->isPreviewable())
                                <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="size-full object-cover">
                            @elseif ($existingImage)
                                <img src="{{ Storage::disk('public')->url($existingImage) }}" alt="Current image" class="size-full object-cover">
                            @else
                                <flux:icon.photo class="size-7 text-stone-400" />
                            @endif

                            {{-- wire:loading shows this overlay only while the file is uploading --}}
                            <div wire:loading.flex wire:target="image" class="absolute inset-0 items-center justify-center bg-white/70 dark:bg-zinc-900/70">
                                <flux:icon.loading class="size-5" />
                            </div>
                        </div>

                        <div class="min-w-0 flex-1 space-y-1">
                            {{-- wire:model on a file input uploads the file as soon as it is picked --}}
                            <flux:input type="file" wire:model="image" accept="image/*" />
                            <flux:text class="text-xs">JPG, PNG or WEBP, up to 1 MB.{{ $existingImage ? ' Pick a new file to replace the current image.' : '' }}</flux:text>
                        </div>
                    </div>
                    <flux:error name="image" />
                </flux:field>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    {{-- wire:loading.attr="disabled" = can't submit while the image is still uploading --}}
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="image">
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
                    You're about to delete <strong>{{ $selectedMenuName }}</strong>.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="deleteMenu" variant="danger">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
