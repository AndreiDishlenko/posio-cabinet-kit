<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	{{-- Фиксированный масштаб гасит автоматический зум Safari при фокусе в поле:
	     иначе поле мельче 16px растягивает страницу и обратно она не отъезжает.
	     Так размер текста в полях остаётся свободным. viewport-fit=cover открывает
	     доступ к безопасным отступам вокруг «чёлки» и индикатора жеста. --}}
	<meta name="viewport" content="width=device-width, minimum-scale=1, maximum-scale=1, initial-scale=1, user-scalable=no, viewport-fit=cover">

	{{-- Название и значок вкладки — из настроек кабинета (раздел «Налаштування
	     кабінету»); пока настройки недоступны, работает имя приложения. --}}
	<title inertia>{{ $site_name ?? config('app.name', 'Cabinet') }}</title>

	@isset($site_favicon)
		<link rel="icon" href="{{ $site_favicon }}">
	@endisset

	{{-- Превью ссылки в мессенджерах: их боты не выполняют скрипты, поэтому теги
	     нужны в исходной разметке — иначе в превью попадает значок вкладки. --}}
	<meta name="description" content="{{ __('User cabinet') }}">
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="{{ $site_name ?? config('app.name', 'Cabinet') }}">
	<meta property="og:title" content="{{ $site_name ?? config('app.name', 'Cabinet') }}">
	<meta property="og:description" content="{{ __('User cabinet') }}">
	@if ( !empty($site_share_image) )
		<meta property="og:image" content="{{ $site_share_image }}">
	@endif

	{{-- Safari до 14.1 не умеет отступ между элементами во flex: объявление молча
	     отбрасывается и вёрстка слипается. Признак ставится замером, а не
	     @supports — тот отвечает «да» из-за поддержки того же свойства в grid.
	     Скрипт обязан выполниться до подключения стилей, иначе первый кадр уедет. --}}
	<script>
	(function () {
		var html  = document.documentElement;
		var probe = document.createElement('div');
		probe.style.cssText = 'display:flex;flex-direction:column;row-gap:1px;position:absolute;visibility:hidden';

		for (var i = 0; i < 2; i++) {
			var child = document.createElement('div');
			child.style.height = '1px';
			probe.appendChild(child);
		}

		html.appendChild(probe);
		var height = probe.scrollHeight;
		probe.parentNode.removeChild(probe);

		// 3 — отступ применён, 2 — отброшен. Меньше двух означает, что образец не
		// попал в раскладку (документ ещё без тела); тогда решает косвенный признак:
		// сокращённая запись сторон появилась в тех же версиях, что и отступ во flex.
		var supported = height >= 3
			|| (height < 2 && window.CSS && CSS.supports && CSS.supports('inset', '0px'));

		if (!supported) html.className += ' no-flex-gap';
	})();
	</script>

	{{-- Класс темы ставим до отрисовки — иначе будет вспышка светлой темы,
	     пока не смонтируется Vue. Свой выбор посетителя приоритетнее темы,
	     заданной оператором в настройках кабинета. --}}
	<script>
	(function () {
		var html  = document.documentElement;
		var saved = localStorage.getItem('theme');

		html.classList.add(saved === 'light' || saved === 'dark' ? saved : '{{ $site_theme ?? 'dark' }}');
	})();
	</script>

	{{-- Ziggy's route() helper — CabinetKit pages use it for every link/post. --}}
	@routes
	{{-- Директива объявляет конфиг маршрутов через const, а WebKit до 14 не отдаёт
	     модулям глобальные лексические привязки классического скрипта — модульная
	     копия Ziggy остаётся без маршрутов. Дублируем конфиг свойством окна. --}}
	<script type="text/javascript">window.Ziggy = typeof Ziggy !== 'undefined' ? Ziggy : window.Ziggy;</script>
	@vite(config('cabinet-kit.vite_entry', 'resources/_admin/js/cabinet.ts'))
	@inertiaHead
</head>
<body>
	@inertia
</body>
</html>
