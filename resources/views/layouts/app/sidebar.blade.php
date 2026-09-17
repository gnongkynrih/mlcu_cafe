<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body>
        <flux:toast />
        {{ $slot }}
       
        @fluxScripts
    </body>
</html>
