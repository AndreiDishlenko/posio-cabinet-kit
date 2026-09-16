@extends('cabinet-kit::mail.layout')

@section('body')
## {{ __('cabinet-kit::mail.registration_approved.heading') }}

{{ __('cabinet-kit::mail.registration_approved.intro', ['site' => $siteName]) }}

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>
@endsection
