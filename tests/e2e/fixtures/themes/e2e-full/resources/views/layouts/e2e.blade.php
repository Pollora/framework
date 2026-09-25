{{-- Every template names itself, so a test can tell which one rendered without the marker. --}}
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @php(wp_head())
</head>
<body>
    <main data-e2e-view="@yield('view')">@yield('view')</main>
    @php(wp_footer())
</body>
</html>
