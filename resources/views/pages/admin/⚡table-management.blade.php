<?php

use Livewire\Component;
use App\Models\CafeTable;

new class extends Component
{
    public $tables;
    public function mount(){
        $this->tables = CafeTable::all();
    }
};
?>

<div class="bg-white max-w-7xl rounded-lg shadow-md mx-auto p-4 text-slate-800">
    <div class="grid grid-cols-4 gap-4">
        @forelse($tables as $table)
            <flux:card size="sm" class="{{$table->status=='available' ? 'bg-emerald-300' : 'bg-indigo-300'}} hover:shadow-lg">
                <flux:heading class="flex items-center gap-2">{{$table->name}} 
                    <p class="rounded-full bg-purple-600 text-white ml-auto text-center p-3">4</p>
                </flux:heading>
                <flux:text class="mt-2">{{$table->status}}</flux:text>
            </flux:card>
        @empty
            <h2>No table</h2>
        @endforelse
    </div>
</div>