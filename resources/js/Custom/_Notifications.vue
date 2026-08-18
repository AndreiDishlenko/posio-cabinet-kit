<template lang="">

    <Dropdown class="h-full"
		ref="dropdown"
		:downOnClick="notifications.length ? true : false"
		:buttonclass = "'h-full v-center'"
		:dropareaclass = "'max-w-[250px] p-4  !max-h-[330px] '"
		@opened = ""
		@closed = "setRead()"
		>       

        <template #button>
			<div class="">
				<!-- {{unreadCount}} -->
				<Icon icon="solar:bell-linear" class="icon" :class="iconclass"/>
				<div v-if="unreadCount" class="icon-alert v-center text-xs">{{ unreadCount }}</div>
			</div>
            <!-- <Icon v-if="hasUnread" icon="ion:alert" class="icon icon-alert"/> -->
            <!-- <Icon :icon="locales[locale] ? locales[locale].icon : ''" class="icon"/> -->
        </template>

        <template #dropdownitems class="dropdown">

			<div class="space-y-5 scrolled-wrapper scrollbar">
				<!-- <div class="text-center text-secondary text-sm">Notifications</div> -->
				<template v-if="notifications.length">
					<template v-for="(item, index) in notifications">
						<div class="flex space-x-2 items-center px-1 " :class="{'text-secondary':item.is_read}">
							<div class="" >
								<Icon icon="ic:outline-shopping-cart" class="icon" :class="{'text-success':!item.is_read}"/>
							</div>

							<div class="v-flex">
								<!-- <div class="leading-[1.5] text-md">{{ item.data }}</div> -->
								<div class="leading-[1.5] text-md" :class="{'underline cursor-pointer':item.type=='order'}" @click="clickItem(item)">{{ item.text }}</div>
								<div class="text-xs text-secondary leading-[1.5]">{{ $dayjs(item.datetime).format('DD.MM.YYYY H:m:s') }}</div>
							</div>	
						</div>
					</template>
				</template>
				<template v-else>
					<div class="">No current notifications</div>
				</template>
			</div>

			<!-- Show more -->
			<!-- <div v-if="notifications.length > notif_count" class="v-center">
				<button class="button button-xs" @click="notif_count = notif_count<=12 ? notif_count+3 : notif_count">
					...
				</button>
			</div> -->

        </template>

    </Dropdown>

</template>


<script>
    import { Icon } from "@iconify/vue";
    import { router } from "@inertiajs/vue3"
    
    import { MenuItem } from '@headlessui/vue'
    import Dropdown from "../../js/Elements/Dropdown.vue";
    import DropdownItem from "../../js/Elements/DropdownItem.vue";

	export default {
        components: {Icon, MenuItem, Dropdown, DropdownItem},
		props: {
			iconclass: {
				type: String,
				default: 'icon-lg'
			}
		},
        data: function() {
            return {
                // messages: this.$dictionaries.messages || []
				notif_count: 5,
				channel: null,
            }
        },
		async mounted() {           
        },
        computed: {
            notifications() {
				// console.log('Current notifications', this.$page.props.user?.notifications);
                let result = [];

				if ( !this.$page.props?.user?.notifications )
					return [];

				this.$page.props.user.notifications.forEach(item => {
                    result.push(item);
                })

                return result;
			},
            unreadCount() {
				// console.log('unreadCount', this.notifications);
				if ( !this.$page.props?.user?.notifications )
					return [];

                let result = 0;

                this.$page.props.user.notifications.forEach(item => {
                    if (item.is_read==false)
                        result++;
                })

                return result;
            },
			// Имя канала должно совпадать с бэкендом: WebSocketService добавляет
			// суффикс окружения (".local"/".staging" и т.п.) вне production.
			notification_channel() {
				const env = document.querySelector('meta[name="app-env"]')?.content || 'production';
				let name = `user.${this.$page.props.user.id}`;
				if ( env !== 'production' )
					name += `.${env}`;
				return name;
			}
        },
		mounted() {
			this.channel = window.Echo.private(this.notification_channel)
				.listen('.notification.new', (e) => {
					// console.log('new notification', e);
					if ( !this.$page.props.user.notifications )
						return;
				
					this.$page.props.user.notifications.unshift(e)
					this.newMessageSound()
				})
				.listen('.notifications.readed', (e) => {
					// console.log('current notifications', this.$page.props.user.notifications);
					
					if ( !this.$page.props.user.notifications )
						return;

					this.$page.props.user.notifications.forEach(notification => { 
						notification.is_read = true; 
					})
				});

			// console.log('Subscribed to', `user.${this.$page.props.user.id}`, 'channel');
		},
		beforeUnmount() {
            if (this.channel) {
                this.channel.stopListening('.notification.new');
				this.channel.stopListening('.notifications.readed');
				window.Echo.leave(this.notification_channel);
			}
        },
		methods: {			
			setRead() {
                // console.log('setRead');
                
				// let result = this.$apiClient.post( route('cabinet.messages.read'), {} )
                // if ( result.error )
                //     return this.$toast.error( result.message );

                // return this.$toast.success('Logs requested');

                // this.messages.forEach(item=>{
                //     if (item.id==msgId && item.is_read==false) {
                //         item.is_read = true;

				router.post( 
					route('cabinet.messages.read'), 
					{},
					{ headers: { 'X-Inertia-Skip-Pause': '1' } }
				);
                //     }
                // })
			},
            acceptInvite(invite) {
                // console.log('acceptInvite', invite.token);
                if ( !invite.token )
                    this.$toast.error( this.$t('The invitation has already been used. Please request access from the owner again.') )

                router.visit( route('cabinet.invite.accept'), {
                        method: 'get',
                        data: {
                            token: invite.token
                        },
                        preserveScroll: true,
                        preserveState: true,
                        onFinish: (page) => {
                            this.setRead(invite.id);
                        },
                    })
                // router.get( route('cabinet.invite.accept'), {token: invite.token} );
            },
			newMessageSound() {
				if ( !this.$page.props.user.play_notifications )
					return;
				
				const orderSound = new Audio('/cabinet-assets/sounds/pay-success.mp3');
				orderSound.play()
				// .catch(err => {
				// 	let result = this.$popup.confirm_yn( this.$t('Do you whant to recieve new order sound confirmation?') );
				// 	if (result)
				// 		orderSound.play()
				// });
			},
			clickItem(notification) {
				// console.log('Notifications.clickItem', notification.data);
				if ( notification.type=='order' ) {
                    router.visit(route('cabinet.docs.orders'), {
                        method: 'get',
                        data: {
                            date: notification.data.order_date || '' //this.$dayjs(notification.datetime).format('YYYY-MM-DD')
                        }
                    });
					console.log(notification.data);					
				}

				return false;
			}
        },
	}
</script>

<style lang="scss" scoped>
    .dropdown {
        width: 300px;
    }
    .header-item {
        /* border-color: var(--divider-color); */
    }
    .invite-submit {
        width:18px;
        height:18px;
        color: var(--success-color);
    }
    .icon-alert {
        width: 15px;
        height: 15px;
        color: var(--error-color);

        position: absolute;
        top: 2px;
        right: -12px;
        /* top: -5px;
        right: -10px; */
        background-color: var(--error-color);
        color: var(--text-color);
        border-radius: 50%;
        // font-size: var(--text-xs);
        // font-weight: bold;
        padding: 0px;
        line-height: 15px;

    }
    .disabled {
        color: var(--text-color-secondary);
    }
</style>
