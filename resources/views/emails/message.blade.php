@extends('emails.layout', ['colors' => $colors, 'logoSrc' => $logoSrc])

@section('content')
{!! $bodyHtml !!}
@endsection
