@extends($activeTemplate . 'layouts.frontend')
@section('content')

    @include($activeTemplate . 'sections.faq')

    @if ($sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include($activeTemplate . 'sections.' . $sec)
        @endforeach
    @endif
@endsection
