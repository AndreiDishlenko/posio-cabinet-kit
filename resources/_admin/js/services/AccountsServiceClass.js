
export class AccountsServiceClass {
	

	constructor($dictionaries) {		
		this.$dictionaries = $dictionaries;
		this.current_account = {}
		this.channel = null
	}

	// currentAccount() {
	// 	return this.current_account;
	// }

	async setAccount(new_account) {
		// console.log('AccountService.setAccount', new_account);

		if ( !new_account || !Object.keys(new_account).length )
			return false;

		if ( this.current_account?.id && this.current_account.id == new_account?.id )
			return false;

		this.current_account = new_account;

		this.handleWebsocketEvent();

		if ( Object.keys(this.$dictionaries.getProperties()).length ) 
			this.$dictionaries.update()
		else 
			await this.$dictionaries.update();
		
		// await this.$dictionaries.update();
	}


	handleWebsocketEvent() {
		this.unhandleWebsocketEvent();

		if (!window.Echo?.private || !this.current_account?.id)
			return;

		// Имя канала должно совпадать с бэкендом: WebSocketService добавляет
		// суффикс окружения (".local"/".staging" и т.п.) вне production, чтобы
		// dev/staging не пересекались с продом на общем broadcast-сервере.
		const env = document.querySelector('meta[name="app-env"]')?.content || 'production';
		let channel_name = `account.${this.current_account.id}`;
		if ( env !== 'production' )
			channel_name += `.${env}`;

		// UPDATE dictionary while another remote user has changed it
		this.channel = window.Echo.private(channel_name)
			.listen('.dictionary.updated', (e) => {
					// console.log('dictionary update', e);
					if ( e?.dict_name ) 
						this.$dictionaries.update(e.dict_name);
				}
			);
	}

	unhandleWebsocketEvent() {
		if (!this.channel)
			return false;

		if (this.channel) 
			this.channel.stopListening('.dictionary.updated');
	}
}
