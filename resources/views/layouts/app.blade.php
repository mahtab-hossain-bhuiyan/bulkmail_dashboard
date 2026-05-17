<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f3f4f6; }
            * { box-sizing: border-box; }
        </style>
    </head>
    <body>
        @include('layouts.navigation')

        @isset($header)
            <header style="background: white; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <div style="max-width: 80rem; margin: 0 auto; padding: 1.5rem;">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main>
            {{ $slot }}
        </main>
    </body>
</html>