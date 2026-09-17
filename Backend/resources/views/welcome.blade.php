<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Dynamic Fonts directive from Laravel plugin -->
        @fonts

        <!-- Directive pour activer le Fast Refresh React avec Vite -->
        @viteReactRefresh
        <!-- Inclusion des assets CSS et JSX gérés par Vite -->
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] antialiased">
        <!-- Conteneur racine sur lequel React va monter ton application -->
        <div id="app"></div>
    </body>
</html>