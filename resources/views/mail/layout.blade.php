{{-- Общая рамка сервисных писем: шапка и подвал с названием сайта из настроек, а не из конфига приложения. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $siteName }}
</x-mail::header>
</x-slot:header>

@if (filled($user->name ?? null))
# {{ __('cabinet-kit::mail.greeting', ['name' => $user->name]) }}
@else
# {{ __('cabinet-kit::mail.greeting_anonymous') }}
@endif

@yield('body')

{{ __('cabinet-kit::mail.signoff') }}<br>
{{ $siteName }}

<x-slot:subcopy>
<x-mail::subcopy>
{{ __('cabinet-kit::mail.fallback_link', ['action' => $actionText]) }}
<span class="break-all">[{{ $actionUrl }}]({{ $actionUrl }})</span>
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $siteName }}. {{ __('cabinet-kit::mail.rights') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
