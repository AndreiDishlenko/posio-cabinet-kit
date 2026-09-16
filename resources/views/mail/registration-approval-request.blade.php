@extends('cabinet-kit::mail.layout')

@section('body')
## {{ __('cabinet-kit::mail.registration_approval_request.heading') }}

{{ __('cabinet-kit::mail.registration_approval_request.intro', ['site' => $siteName, 'name' => $registeredUser->name, 'email' => $registeredUser->email]) }}

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

{{ __('cabinet-kit::mail.registration_approval_request.note') }}
@endsection
