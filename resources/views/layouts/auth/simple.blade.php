<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class='bg-white text-black' >
        hi hello
        <flux:toast />
        <div>
        <a href="{{ route('contact-us') }}">Contact Us</a> | 
        <a href="{{ route('about') }}">About</a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                    
                </div>
                <div>THis is the footer</div>
        </div>

        @fluxScripts
    </body>
</html>
