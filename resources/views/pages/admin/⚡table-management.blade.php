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

<div class="bg-white max-w-7xl rounded-lg shadow-md mx-auto p-4 text-slate-800">
    <div x-data="{ open: false }" @close-the-form.window="open = false">
        <div class="flex justify-end">
            <flux:button @click="open = true" variant="outline" color="blue" class="mb-4" icon="plus">
                Add New Table
            </flux:button>
            
        </div>
        <flux:card x-show="open" x-transition.duration.300ms size="sm" class="bg-gray-800 p-4 rounded-lg max-w-2xl mx-auto">
            <form wire:submit="save">
                <flux:input class="mb-2" wire:model="name" label="Table Name" description="Enter the table number" />
                <flux:input class="mt-2" wire:model="capacity" type="number" label="Seating Capticy" description="Number of person" />
                <div class="space-y-2 flex justify-between gap-4 mt-4">
                    
                    <flux:button variant="ghost" class="w-full" @click="open = false">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" class="w-full">Save</flux:button>
                </div>
            </form>
            
        </flux:card>

        <div x-show="!open" x-transition.duration.300ms  class="grid grid-cols-3 md:grid-cols-4 gap-4">
            @forelse($tables as $table)
                <flux:card size="sm" class="bg-indigo-400 hover:shadow-lg hover:bg-indigo-600">
                    <flux:heading class="flex items-center gap-2 text-white">{{$table->name}} 
                        <p class="rounded-full w-8 h-8 text-center {{$table->status=='available' ? 'bg-emerald-300' : 'bg-red-300'}} text-white ml-auto text-center p-1 md:p-2">{{$table->capacity}}</p>
                    </flux:heading>
                    <div class="flex justify-between items-center border-t border-gray-200 pt-2 mt-4">
                            <flux:text class="text-white">{{$table->status}}</flux:text>
                            <div class="flex gap-2">
                                <flux:icon 
                                wire:click="delete({{$table->id}})"  
                                name="trash" 
                                class="w-8 h-8 bg-red-400 text-white rounded-full p-1" />
                                <flux:icon 
                                    @click="open = true" 
                                    wire:click="edit({{$table->id}})"  
                                    name="pencil" 
                                    class="w-8 h-8 bg-amber-400 text-white rounded-full p-1" />
                            </div>
                    </div>
                </flux:card>
            @empty
                <h2>No table</h2>
            @endforelse
        </div>
    </div>
    

    <flux:modal wire:model.self="showDeleteConfirm" name="delete-profile" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Table?</flux:heading>
                <flux:text class="mt-2">
                    Are you sure you want to delete the Table
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="confirmDelete()" type="button" variant="danger">Yes</flux:button>
            </div>
        </div>
    </flux:modal>
</div>