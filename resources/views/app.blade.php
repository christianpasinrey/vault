<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- A password vault has no business showing up in a search index. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name', 'Vault') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
