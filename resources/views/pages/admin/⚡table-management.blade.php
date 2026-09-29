<?php

use Livewire\Component;
use App\Models\CafeTable;
use Flux\Flux;
new class extends Component
{
    public $tables;
    public $name;
    public $capacity;
    public $selectedTableid;
    public $showDeleteConfirm = false;
    public function mount(){
        $this->tables = CafeTable::all();
    }
    public function save(){
       if($this->selectedTableid){
            $cafe = CafeTable::findOrFail($this->selectedTableid);
        }else{
            $cafe = new CafeTable;
        }
        $cafe->name=$this->name;
        $cafe->capacity = $this->capacity;
        $cafe->save();

        $this->tables = CafeTable::all();
        $this->name = '';
        $this->capacity = '';
        $this->selectedTableid = null;
        // In Livewire, dispatch is the mechanism for sending custom events from a component so that other 
        // parts of your page (other Livewire components, Alpine.js code, or plain JavaScript) 
        // can react to them
        $this->dispatch('close-the-form');
    }

    public function edit($id){
        $this->selectedTableid = $id;
        $cafe = CafeTable::findOrFail($id);
        $this->name = $cafe->name; 
        $this->capacity =$cafe->capacity;
    }
    
    public function delete($id){
        $this->selectedTableid = $id;
        $this->showDeleteConfirm = true;

    }
    public function confirmDelete(){
        CafeTable::findOrFail($this->selectedTableid)->delete();
        Flux::toast('Table deleted successfully');
        $this->selectedTableid = false;
        $this->showDeleteConfirm = false;
        $this->tables = CafeTable::all();
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">
    {{--
        x-data="{ open: false }" = Alpine.js state that shows/hides the form.
        @close-the-form.window = listens for the 'close-the-form' event that
        save() dispatches from PHP, then hides the form.
    --}}
    <div x-data="{ open: false }" @close-the-form.window="open = false" class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl" level="1">Table Management</flux:heading>
                    <flux:badge color="amber" size="sm">{{ count($tables) }} {{ Str::plural('table', count($tables)) }}</flux:badge>
                </div>
                <flux:text class="mt-1">Add, edit and remove the tables in your cafe.</flux:text>
            </div>

            <flux:button x-show="!open" @click="open = true" variant="primary" icon="plus">
                Add New Table
            </flux:button>
        </div>

        {{-- Add / Edit form --}}
        <div x-show="open" x-cloak x-transition.duration.300ms class="mx-auto max-w-lg">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $selectedTableid ? 'Edit Table' : 'New Table' }}</flux:heading>
                    <flux:text class="mt-1">Give the table a name and how many people it seats.</flux:text>
                </div>
                <form wire:submit="save" class="space-y-4">
                    <flux:input wire:model="name" label="Table Name" description="e.g. T1, Window 2, Patio" />
                    <flux:input wire:model="capacity" type="number" min="1" label="Seating Capacity" description="Number of people" icon="users" />
                    <div class="flex justify-end gap-2 pt-2">
                        <flux:button type="button" variant="ghost" @click="open = false">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Save</flux:button>
                    </div>
                </form>
            </flux:card>
        </div>

        {{-- Tables grid --}}
        <div x-show="!open" x-transition.duration.300ms class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @forelse($tables as $table)
                <div wire:key="table-{{ $table->id }}" class="group rounded-xl border border-stone-200 bg-white p-4 shadow-xs transition hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex size-11 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            <flux:icon.squares-2x2 class="size-6" />
                        </div>
                        <flux:badge size="sm" :color="$table->status == 'available' ? 'emerald' : 'red'">
                            {{ ucfirst($table->status) }}
                        </flux:badge>
                    </div>

                    <flux:heading size="lg" class="mt-3 truncate">{{ $table->name }}</flux:heading>
                    <flux:text class="flex items-center gap-1 text-sm">
                        <flux:icon.users variant="micro" /> Seats {{ $table->capacity }}
                    </flux:text>

                    <div class="mt-4 flex justify-end gap-1 border-t border-stone-100 pt-3 dark:border-zinc-800">
                        <flux:tooltip content="Edit">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                                @click="open = true"
                                wire:click="edit({{ $table->id }})"
                            />
                        </flux:tooltip>
                        <flux:tooltip content="Delete">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                class="text-red-500! hover:bg-red-50! dark:hover:bg-red-500/10!"
                                wire:click="delete({{ $table->id }})"
                            />
                        </flux:tooltip>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-stone-300 px-6 py-16 text-center dark:border-zinc-700">
                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-500/10">
                        <flux:icon.squares-2x2 class="size-7 text-amber-500" />
                    </div>
                    <flux:heading size="lg">No tables yet</flux:heading>
                    <flux:text class="mt-1">Add your first table to start taking orders.</flux:text>
                </div>
            @endforelse
        </div>
    </div>

    <flux:modal wire:model.self="showDeleteConfirm" name="delete-profile" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete table?</flux:heading>
                <flux:text class="mt-2">
                    Are you sure you want to delete this table?<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="confirmDelete()" type="button" variant="danger">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
