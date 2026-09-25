// Розмірний проп ("xs", "md sm:lg", "lt-sm:xs sm:md") розгортається у класи
// table-[size]/row-[size] з тим самим брейкпоінт-префіксом, що й у вихідному токені.
export function sizePropClasses(value, className) {
	if ( !value )
		return [];

	return value.trim().split(/\s+/).filter(Boolean).map(token => {
		const parts = token.split(':');
		const size = parts.pop();
		const breakpoint = parts.join(':');

		return breakpoint ? `${breakpoint}:${className}-${size}` : `${className}-${size}`;
	});
}
