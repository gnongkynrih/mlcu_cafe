<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class='bg-white text-black' >
        <flux:toast />
        <div>
       
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                    
                </div>
        </div>

        @fluxScripts
    </body>
</html>
