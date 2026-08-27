// Действия вложенного блока (вкладки отчёта) попадают в меню пользователя:
// сам блок шапки не видит, поэтому регистрируется в реестре страницы.
// Требование к компоненту: computed или data-свойство page_menu — список
// пунктов { name, icon?, action | href, disabled?, in_burger? }.
export default {
	inject: {
		pageMenuRegistry: { default: null },
	},
	mounted() {
		if (this.pageMenuRegistry)
			this.pageMenuRegistry.register(this);
	},
	beforeUnmount() {
		if (this.pageMenuRegistry)
			this.pageMenuRegistry.unregister(this);
	},
}
