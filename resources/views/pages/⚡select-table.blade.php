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

<div class="min-w-7xl bg-slate-50 min-h-screen p-4 mx-auto rounded-lg">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @foreach($tables as $table)
            <flux:card wire:click="selectTable({{ $table['id'] }})" class="{{$table['status']=='available' ? 'bg-emerald-400' : 'bg-red-400'}} min-h-44 hover:bg-slate-500">
                {{ $table['name']}}
        
            </flux:card>
        @endforeach
    </div>

    <flux:modal wire:model.self="showModal" name="select-table-modal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Select Table</flux:heading>
                <flux:text class="mt-2">Select a table to proceed.</flux:text>
            </div>
            <flux:input wire:model="name" label="Customer Name" placeholder="Your name" />
            <flux:input wire:model="guests" label="Number of Guests" type="number" />
            <flux:select wire:model="orderType" label="Order Type">
                <flux:select.option value="dine">Dine In</flux:select.option>
                <flux:select.option value="takeout">Takeout</flux:select.option>
            </flux:select>
            <div class="flex">
                <flux:spacer />
                <flux:button type="button" wire:click="takeOrder" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
</div>