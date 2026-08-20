<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title inertia>{{ config('app.name', 'Cabinet') }}</title>

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
	     пока не смонтируется Vue. --}}
	<script>
	(function () {
		var html = document.documentElement;
		html.classList.add(localStorage.getItem('theme') === 'light' ? 'light' : 'dark');
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
