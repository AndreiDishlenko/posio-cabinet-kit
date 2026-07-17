<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title inertia>{{ config('app.name', 'Cabinet') }}</title>

	{{-- Ziggy's route() helper — CabinetKit pages use it for every link/post. --}}
	@routes
	@vite(config('cabinet-kit.vite_entry', 'resources/_admin/js/admin.js'))
	@inertiaHead
</head>
<body>
	@inertia
</body>
</html>
