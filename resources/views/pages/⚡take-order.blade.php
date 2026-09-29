<?php

use Livewire\Component;
use App\Models\Category;
use App\Models\MenuItem;
new class extends Component
{
    public $info;
    public $categories = [];
    public $menuItems = [];
    public function mount(){
        //check if the session order is there or not
        if(!session('order')){
            return $this->redirect(route('select-table'));
        }
        $this->info = session()->get('order');

        //get all the available food categories
        $this->categories = Category::select(['name','id'])->where('is_active',1)->orderBy('sort_order')->get()->toArray();
    }

    public function showMenuItems($categoryId = null){
        $sql = MenuItem::where('is_available',true);
        if($categoryId){
            $sql = $sql->where('category_id',$categoryId);
        }
        $this->menuItems = $sql->get();
    }
};
?>

<div class="min-w-5xl    bg-slate-50 min-h-screen p-4 mx-auto rounded-lg text-slate-800">
    <flux:card class="bg-purple-500 text-white p-4 m-2 rounded-lg flex justify-between">
        <div>Table No: {{ $info['table_name']}}</div>
        <div>
            @if($info['customer_name'])
                <p>Customer: {{$info['customer_name']}}</p>
            @endif
            <p>Number of Guests : <span class="w-8 h-8 rounded-full bg-pink-500 inline-block text-center"> {{$info['guests']}}</span></p>
        </div>
    </flux:card>
    <div class="flex">
        <div class="w-[65%]">
             <flux:card class="bg-slate-200 text-slate-800 p-4 m-2 rounded-md flex  overflow-x-auto">
                <div wire:click="showMenuItems()"  class="p-2 rounded-sm bg-emerald-400 ml-4 min-w-24 text-center hover:bg-emerald-800 hover:text-white">All</div>
                @foreach($categories as $cat)
                    <div wire:click="showMenuItems({{$cat['id']}})" class="p-2 rounded-sm hover:bg-emerald-800 hover:text-white bg-emerald-400 ml-4 min-w-24 text-center">{{$cat['name']}}</div>
                @endforeach
            </flux:card>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @forelse($menuItems as $menu)
                    <div class="p-4 bg-slate-100">
                        <div class="flex justify-between items-center">
                            <p>{{$menu->name}}</p>
                            <p> ₹{{$menu->price}}</p>
                        </div>
                        <div class="flex justify-between mt-4">
                            <flux:icon class="w-8 h-8 rounded-full bg-red-400 text-white" name="minus"></flux:icon>
                            <p>0</p>
                            <flux:icon class="w-8 h-8 rounded-full bg-emerald-400 text-white"  name="plus"></flux:icon>
                        </div>
                    </div>
                @empty
                    <div>No menu for the selected category</div>
                @endforelse
            </div>
        </div>
        <div class="bg-purple-300 w-[35%] p-4 m-2 rounded-md min-h-96">
            Order Summary
        </div>
    </div>
</div>