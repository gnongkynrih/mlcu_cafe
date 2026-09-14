<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('About')] class extends Component
{
    //
};
?>

<div>
    <p class="pt-10 pb-4 pl-4 m-2 text-2xl font-bold text-yellow-300 bg-gray-800">This is an About Page</p>

    <div>Testing</div>
</div>
