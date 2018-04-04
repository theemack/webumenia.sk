<!DOCTYPE html>
<html lang="sk">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <meta name="author" content="lab.SNG">
  <meta name="robots" content="noindex, nofollow">

  <title>
      {!! $item->title !!} | {{ trans('zoom.title') }}
  </title>

  <!--  favicons-->
  @include('includes.favicons')
  <!--  /favicons-->
  <!--  Open Graph protocol -->
  @include('includes.og_tags')
  <!--  Open Graph protocol -->

  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
  {!! Html::style('css/style.css') !!}
 </head>

 <body class="template-zoom">
  @include('components.zoomviewer', ['item' => $item, 'related_items' => $related_items])
  @include('components.zoomviewer_js', ['item' => $item, 'related_items' => $related_items])
 </body>

</html>