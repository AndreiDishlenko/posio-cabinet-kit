// Протягивание содержимого вниз, раскрывающее скрытую над ним строку, — тем же
// жестом, каким в мобильных приложениях вытягивают панель. Управляемая величина
// одна: высота строки. Чем она нарисована и где живёт, знает только потребитель —
// сюда он отдаёт набор действий над ней.

// Порог, после которого движение считается протягиванием, а не касанием.
const DRAG_SLOP      = 6;
// Насколько строка поддаётся за пределом раскрытия, прежде чем упереться.
const OVERSHOOT      = 28;
// Скорость (px/мс), с которой короткий взмах решает исход вместо пройденного пути.
const FLING_VELOCITY = 0.35;
// Границы длительности доводки.
const SETTLE_MIN     = 140;
const SETTLE_MAX     = 320;

// Ожидаемые действия:
//   enabled()       — доступен ли жест сейчас (телефонная раскладка и т.п.)
//   boundary()      — узел, выше которого прокручиваемого предка не искать
//   fullHeight()    — высота полностью раскрытой строки
//   currentHeight() — отрисованная высота прямо сейчас
//   begin()         — подготовить строку к ведению; false отменяет жест
//   setHeight(px)   — записать высоту (вызывается не чаще кадра)
//   settle(open, duration) — довести строку до края после отпускания
export function createPullReveal(actions) {

	let pull  = null;
	let frame = null;

	// Прокручиваемый предок под пальцем: по нему видно, свободен ли жест
	// или его ждёт обычная прокрутка списка.
	function findScroller(target) {
		const boundary = actions.boundary ? actions.boundary() : null;
		let   node     = target;

		while ( node && node.nodeType === 1 && node !== boundary ) {
			if ( node.scrollHeight - node.clientHeight > 1 ) {
				const overflow = window.getComputedStyle(node).overflowY;
				if ( overflow === 'auto' || overflow === 'scroll' )
					return node;
			}

			node = node.parentNode;
		}

		return null;
	}

	// Кромка идёт за пальцем один к одному; за краем раскрытия сопротивление
	// растёт, и строка упирается — как список, растянутый за свой конец.
	function limitHeight(raw) {
		if ( raw <= 0 )
			return 0;

		if ( raw <= pull.full )
			return raw;

		const extra = raw - pull.full;

		return pull.full + OVERSHOOT * extra / (extra + OVERSHOOT);
	}

	// Строку, пойманную посреди доводки, ведём в любую сторону. В покое жест
	// забираем только у списка, прокрученного в начало, и только если строке
	// есть куда двигаться.
	function gestureAllowed(dy) {
		if ( pull.base > 0 && pull.base < pull.full )
			return true;

		if ( pull.scroller && pull.scroller.scrollTop > 0 )
			return false;

		return dy > 0 || pull.base > 0;
	}

	// Запись высоты — раз в кадр: сенсор отдаёт события чаще, чем экран
	// успевает перерисоваться, и лишние записи только пересчитывают раскладку.
	function scheduleFrame() {
		if ( frame )
			return;

		frame = requestAnimationFrame(() => {
			frame = null;

			if ( pull && pull.active )
				actions.setHeight(pull.height);
		});
	}

	function cancelFrame() {
		if ( !frame )
			return;

		cancelAnimationFrame(frame);
		frame = null;
	}

	return {

		start(e) {
			cancelFrame();
			pull = null;

			if ( e.touches.length != 1 )
				return;

			if ( !actions.enabled() )
				return;

			const touch = e.touches[0];
			const base  = actions.currentHeight();

			pull = {
				x:         touch.clientX,
				y:         touch.clientY,
				base:      base,
				full:      actions.fullHeight(),
				height:    base,
				scroller:  findScroller(e.target),
				velocity:  0,
				last_y:    touch.clientY,
				last_time: e.timeStamp,
				active:    false,
				rejected:  false
			};
		},

		move(e) {
			if ( !pull || pull.rejected || !e.touches.length )
				return;

			const touch = e.touches[0];
			const dy    = touch.clientY - pull.y;

			if ( !pull.active ) {
				if ( Math.abs(dy) < DRAG_SLOP )
					return;

				if ( Math.abs(touch.clientX - pull.x) > Math.abs(dy) || !gestureAllowed(dy) ) {
					pull.rejected = true;
					return;
				}

				if ( !actions.begin() ) {
					pull.rejected = true;
					return;
				}

				pull.active = true;
				// Отсчёт с момента захвата — иначе строка прыгнет на величину порога.
				pull.y      = touch.clientY;
			}

			e.preventDefault();

			// Скорость по последним кадрам, сглаженная: по ней короткий взмах
			// доводит строку до конца, не требуя протянуть её всю.
			const dt = e.timeStamp - pull.last_time;
			if ( dt > 0 ) {
				pull.velocity  = pull.velocity * 0.3 + ((touch.clientY - pull.last_y) / dt) * 0.7;
				pull.last_y    = touch.clientY;
				pull.last_time = e.timeStamp;
			}

			pull.height = limitHeight(pull.base + (touch.clientY - pull.y));
			scheduleFrame();
		},

		end(e) {
			const finished = pull;
			pull = null;
			cancelFrame();

			if ( !finished || !finished.active )
				return;

			// Палец, замерший перед отрывом, взмахом не считается.
			const velocity = ( e && e.timeStamp - finished.last_time > 60 ) ? 0 : finished.velocity;

			const open = Math.abs(velocity) > FLING_VELOCITY ?
				velocity > 0 :
				finished.height > finished.full / 2;

			const distance = Math.abs((open ? finished.full : 0) - finished.height);
			let   duration = SETTLE_MIN;

			if ( distance > 0 )
				duration = Math.min(SETTLE_MAX, Math.max(SETTLE_MIN, distance / Math.max(Math.abs(velocity), 0.4)));

			actions.settle(open, duration);
		},

		// Незавершённый кадр иначе допишет высоту в снятый со страницы узел.
		destroy() {
			cancelFrame();
			pull = null;
		},

	};
}
