@extends($activeTemplate . 'layouts.frontend')
@php
    $bannerSectionContent = getContent('banner.content', true);
@endphp
@section('content')
    @include($activeTemplate . 'sections.banner')
    @if (@$sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include($activeTemplate . 'sections.' . $sec)
        @endforeach
    @endif
@endsection
