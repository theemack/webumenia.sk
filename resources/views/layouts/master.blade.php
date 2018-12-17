<!DOCTYPE html>
<html lang="{{ LaravelLocalization::getCurrentLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @section('description')
    <meta name="description" content="{{ trans('master.meta_description') }}">
    @show
    <meta name="author" content="lab.SNG">

    <title>
        @section('title')
        ARTBASE
        @show
    </title>

    <!--  favicons-->
    @include('includes.favicons')
    <!--  /favicons-->

    <!--  Open Graph protocol -->
    @include('includes.og_tags')
    <!--  /Open Graph protocol -->

    <!--  hreflangs -->
    @include('includes.hreflangs', [
      'localizedURLs' => getLocalizedURLArray(),
    ])
    <!--  /hreflangs -->

    @yield('link')

    <!-- CSS -->

    <link rel="stylesheet" href="{{ mix('/css/app.css') }}">


    {{-- JS --}}

    {{-- Google Analytics --}}
    @if (App::environment() == 'production')
    <script>
        {{-- GA tracking code --}}
    </script>
    @endif
</head>
<body class="p-0 bg-white text-black">
    <div class="container-fluid mw-1920">
        <div class="row">
            <div class="col-sm-2">
                @include('components.khb_header')
            </div>
            <div class="col-sm-10 py-0 border-0">
                @include('components.khb_nav_bar')
                @yield('content')
                @include('components.khb_footer')
            </div>
        </div>
    </div>

    <!-- Javascript -->
    <script type="text/javascript" src="{{ mix('js/manifest.js') }}"></script>
    <script type="text/javascript" src="{{ mix('/js/vendor.js') }}"></script>
    <script type="text/javascript" src="{{ mix('/js/app.js') }}"></script>
    @yield('javascript')
</body>
</html>
