@extends('cabinet-kit::mail.layout')

@section('body')
## {{ __('cabinet-kit::mail.verify_email.heading') }}

{{ __('cabinet-kit::mail.verify_email.intro', ['site' => $siteName]) }}

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

{{ __('cabinet-kit::mail.verify_email.ignore', ['site' => $siteName]) }}
@endsection
