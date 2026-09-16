@extends('cabinet-kit::mail.layout')

@section('body')
## {{ __('cabinet-kit::mail.reset_password.heading') }}

{{ __('cabinet-kit::mail.reset_password.intro', ['site' => $siteName]) }}

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

{{ __('cabinet-kit::mail.reset_password.expire', ['minutes' => $expireMinutes]) }}

{{ __('cabinet-kit::mail.reset_password.ignore') }}
@endsection
