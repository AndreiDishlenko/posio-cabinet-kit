import dayjs       	from "dayjs";


// docSerice is general service for documents list forms:
// - RetailSales.vue
// - Orders.vue
// - Purchases.vue
// - ServiceDocuments.vue

export const doclistsService = {

	// Будує payload для дії «копіювати документ/операцію»: клонує рядок, прибирає
	// ідентифікатори / нумерацію / статуси проводки, обнуляє id позицій (cont) і
	// зсуває дату на +1 хв. Спільний для списків документів (Purchases) та журналу
	// фіноперацій (FinancialDocs). `exclude` — додаткові поля, специфічні для форми.
	// Мітка «створено системою» не копіюється: копія — самостійний документ користувача.
	buildCopyPayload(row, exclude = []) {
		const skip = ['id', 'uuid', 'number', 'is_shipped', 'is_paid', 'is_finished', 'is_auto', ...exclude];

		const options = {};
		Object.keys(row).forEach(key => {
			if (!skip.includes(key))
				options[key] = row[key];
		});

		// Позиції копіюємо в нові обʼєкти (без id/doc_id), не мутуючи рядок-джерело.
		if (Array.isArray(options.cont)) {
			options.cont = options.cont.map(record => {
				const copy = { ...record };
				delete copy.id;
				delete copy.doc_id;
				return copy;
			});
		}

		if (options.datetime)
			options.datetime = dayjs(options.datetime).add(1, 'minute').format('YYYY-MM-DD HH:mm:ss');

		return options;
	},

	indicators: {
		0: 'neworder',
		3: 'awaitingpayments',

		15: 'packing',

		10: 'awaitingpickup',		
		11: 'pickedup',

		12: 'awaitingdelivery',
		6:	'outfordelivery',
		7: 	'ontheway',
		14: 'arrived',
		// 9: 	'deliveried',

		5: 'awaitingarrival',

		13: 'finished',
	},

	getStatusIndicator(status_id) {
		return this.indicators[status_id];
	},

	// getDtoStatusObject(status_id) {
	// 	return {
	// 		indicator: this.indicators[status_id],
	// 		doc_status: status_id
	// 	};
	// },

    // getOrderStatus(order) {
	// 	// console.log('getOrderStatus', order.doc_status, Number(order.doc_status));

	// 	// Check static order status
	// 	if ( order.doc_status && Number(order.doc_status) ) 
	// 		return this.getDtoStatusObject(order.doc_status)
		
	// 	// Get automatic status
    //     if ( !order.is_confirmed && !order.is_shipped && !order.is_paid && !order.is_finished)
    //         return this.getDtoStatusObject(0) // New order
	
	// 	// Finish statuses
    //     if ( order.is_shipped && order.is_paid && order.is_finished ) {
	// 		if ( order.delivery_type )
	// 			return this.getDtoStatusObject(13) // Deliveried
	// 		else
	// 			return this.getDtoStatusObject(11) // Picked Up

	// 		// return this.getDtoStatusObject(13) // Finished
	// 	}
            
		
    //     if ( order.payterms_id!=2 && !order.is_paid ) 
    //         return this.getDtoStatusObject(3) // Awaiting Payment 

    //     if ( !order.is_shipped ) {

	// 		if ( order.delivery_type ) {
	// 			// With delivery	
	// 			if ( !order.is_shipped )
	// 				return this.getDtoStatusObject(12) // Awaiting Dispatch  
				
	// 			if ( order.is_shipped && !order.is_finished )
	// 				return this.getDtoStatusObject(7) // On the way   
								
	// 		} else {
	// 			// Without delivery
	// 			return this.getDtoStatusObject(10) // Awaiting Pickup 
	// 		}

	// 	}

    //     return this.getDtoStatusObject(0) // New order
    // },

	// getPurchaseStatus(purchase) {
	// 	// console.log('getPurchaseStatus', purchase.number, purchase.is_shipped, purchase.is_paid);
				
	// 	// Check static purchase status
	// 	// if ( order.doc_status ) 
	// 	// 	return this.getDtoStatusObject(purchase.doc_status)
		
	// 	// Get automatic status
	// 	if ( !purchase.is_shipped && !purchase.is_paid )
    //         return this.getDtoStatusObject(0) // New purchase
		
    //     if ( purchase.is_finished ) 
	// 		return this.getDtoStatusObject(13) // Finished
		
    //     if ( !purchase.is_paid && (purchase.payterms_id!=2 || purchase.is_shipped)  ) 
    //         return this.getDtoStatusObject(3) // Awaiting Payment 
		
    //     if ( purchase.payterms_id==2 && purchase.is_shipped ) 
    //         return this.getDtoStatusObject(3) // Awaiting Payment 

	// 	return this.getDtoStatusObject(14) // Awaiting arrival
	// },

	// getStatus(order, statuses_dictionaries) {
		// if ( !statuses_dictionaries.find(i => i.id == order.status) )
		// 		return {
		// 			status_id: 0,
		// 			indicator: ''
		// 		}
	// }

}
