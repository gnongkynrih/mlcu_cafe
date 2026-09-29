<?php
use App\Models\CafeTable;
use App\Models\Order;
use Livewire\Component;

new class extends Component
{
    public $tables;
    public $selectedTableName;
    public $selectedTableId;
    public $showModal = false;
    public $name;
    public $guests = 1;
    public $orderType = 'dine';
    public $orderId = null;
    public function mount(){
        //clear the session
        session()->put('order',null);
        $this->selectedTableId = null;
        $this->name = null;
        $this->guests = 1;
        $this->tables = CafeTable::where('is_active',true)->get()->toArray();
    }
    public function selectTable($id){
        $this->selectedTableId = $id;
        //get the name of the table base on the id
        //convert the array into a collection
        $table = collect($this->tables)->firstWhere('id',$id);
        $this->selectedTableName = $table['name'];

        //check if the table is open i.e someone is already sitting
        //select * from orders where status=open and cafe_table_id=id sort by id desc limit 1
        $order = Order::where('status','open')->where('cafe_table_id',$id)->latest()->first();
        if($order){
            $this->name = $order->customer_name;
            $this->guests = $order->guests;
            $this->orderType = $order->order_type;
            $this->orderId = $order->id;
        }else{
            $this->name = '';
            $this->guests = 1;
            $this->orderId = null;
        }

        $this->showModal = true;
    }

    public function takeOrder(){
       
        //store into the order table
        $order = Order::updateOrCreate(
            ['id' => $this->orderId],     //condition it checks if the orderid exist, if so it updates else insert
            [
                'order_number' => time(),
                'cafe_table_id' => $this->selectedTableId,
                'customer_name' => $this->name,
                'guests' => $this->guests,
                'status' => 'open',
                'order_type' => $this->orderType,
                'subtotal' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 0
            ]);

            //THIS IS ANOTHERWAY OF DOING IT
            // $order = Order::findOrFail($this->orderId);
            // if($order != null){
            //     $order->update([

            //     ]);
            // }else{
            //     $order->create([

            //     ]);
            // }
            //OR THIS WAY 
        // if($this->orderId != null){
        //     $order = Order::findOrFail($this->orderId);
        // }else{
        //     $order = new Order();
        // }
        // $order->order_number = time(),
        // $order->cafe_table_id = $this->selectedTableId,
        // $order->customer_name = $this->name,
        // $order->guests = $this->guests,
        // $order->status = open,
        // $order->order_type = $this->orderType,
        // $order->subtotal = 0,
        // $order->discount_amount = 0,
        // $order->tax_amount = 0,
        // $order->total_amount = 0
        // $order->save();

        //update the status of the cafetable
        // CafeTable::findOrFail($this->selectedTableId)->update([
        //     'status'=>'occupied'
        // ]);

        //updates using the relationahip name
        $order->cafeTable()->update([
            'status' =>'occupied'
        ]);

         //store the table id and name in a session
        $data = [
            'order_id' => $order->id,
            'table_id' => $this->selectedTableId,
            'table_name' => $this->selectedTableName,
            'customer_name' => $this->name,
            'guests' => $this->guests,
            'order_type' => $this->orderType
        ];

        // WRITE to session: pass an ARRAY of key=>value pairs
        // session('table', $data) is a GET with default — it does NOT store!
        session()->put('order', $data);
        $this->redirect(route('take-order'));
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Select a Table</flux:heading>
            <flux:text class="mt-1">Tap a table to start a new order or continue an open one.</flux:text>
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-4 text-sm text-stone-600 dark:text-zinc-400">
            <span class="flex items-center gap-2"><span class="size-3 rounded-full bg-emerald-500"></span> Available</span>
            <span class="flex items-center gap-2"><span class="size-3 rounded-full bg-rose-500"></span> Occupied</span>
        </div>
    </div>

    {{-- Tables grid --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @forelse($tables as $table)
            @php($isAvailable = $table['status'] == 'available')
            <button
                type="button"
                wire:key="table-{{ $table['id'] }}"
                wire:click="selectTable({{ $table['id'] }})"
                @class([
                    'group relative flex min-h-36 flex-col justify-between rounded-xl border-2 p-4 text-left transition',
                    'hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500',
                    'border-emerald-200 bg-emerald-50 hover:border-emerald-400 dark:border-emerald-500/30 dark:bg-emerald-500/10' => $isAvailable,
                    'border-rose-200 bg-rose-50 hover:border-rose-400 dark:border-rose-500/30 dark:bg-rose-500/10' => ! $isAvailable,
                ])
            >
                <div class="flex items-start justify-between">
                    <span @class([
                        'flex size-10 items-center justify-center rounded-lg',
                        'bg-emerald-500 text-white' => $isAvailable,
                        'bg-rose-500 text-white' => ! $isAvailable,
                    ])>
                        <flux:icon.squares-2x2 class="size-5" />
                    </span>
                    <span @class([
                        'text-xs font-semibold uppercase tracking-wide',
                        'text-emerald-700 dark:text-emerald-400' => $isAvailable,
                        'text-rose-700 dark:text-rose-400' => ! $isAvailable,
                    ])>
                        {{ $isAvailable ? 'Available' : 'Occupied' }}
                    </span>
                </div>

                <div>
                    <p class="text-xl font-bold text-stone-800 dark:text-zinc-100">{{ $table['name'] }}</p>
                    <p class="flex items-center gap-1 text-sm text-stone-500 dark:text-zinc-400">
                        <flux:icon.users variant="micro" /> Seats {{ $table['capacity'] ?? '—' }}
                    </p>
                </div>
            </button>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-stone-300 px-6 py-16 text-center dark:border-zinc-700">
                <flux:heading size="lg">No active tables</flux:heading>
                <flux:text class="mt-1">Add tables from <flux:link href="{{ route('table-management') }}">Table Management</flux:link> first.</flux:text>
            </div>
        @endforelse
    </div>

    <flux:modal wire:model.self="showModal" name="select-table-modal" class="w-full md:w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $selectedTableName ? 'Table '.$selectedTableName : 'Select Table' }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $orderId ? 'This table already has an open order. Review the details and continue.' : 'Enter the customer details to start a new order.' }}
                </flux:text>
            </div>

            <flux:input wire:model="name" label="Customer Name" placeholder="Customer name (optional)" icon="user" />
            <flux:input wire:model="guests" label="Number of Guests" type="number" min="1" icon="users" />

            <flux:radio.group wire:model="orderType" label="Order Type" variant="segmented">
                <flux:radio value="dine" label="Dine In" />
                <flux:radio value="takeout" label="Takeout" />
            </flux:radio.group>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="takeOrder" variant="primary" icon:trailing="arrow-right">
                    {{ $orderId ? 'Continue Order' : 'Start Order' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
