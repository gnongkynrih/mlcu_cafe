<?php

use Livewire\Component;

new class extends Component
{
    public $info;
    public function mount(){
        //check if the session order is there or not
        if(!session('order')){
            return $this->redirect(route('select-table'));
        }
        $this->info = session()->get('order');
    }
};
?>

<div class="min-w-7xl bg-slate-50 min-h-screen p-4 mx-auto rounded-lg text-slate-800">
    <flux:card>
        Table No: {{ $info['table_name']}}
        @if($info['customer_name'])
            Customer: {{$info['customer_name']}}
        @endif
         Number of Guests : {{$info['guests']}}
    </flux:card>
</div>