<template>

	<Teleport to="body">
		<div v-if="isOpen" class="ck-modal-overlay" @mousedown.self="onOverlayClick">
			<div class="ck-modal-content" :class="cardclasses">
				<slot/>
			</div>
		</div>
	</Teleport>

</template>

<script>
	export default {
		name: 'ModalForm',
		props: {
			escToClose: {
				type: Boolean,
				default: true,
			},
			outsideClickClose: {
				type: Boolean,
				default: true,
			},
			cardclasses: {
				type: String,
				default: '',
			},
		},
		emits: ['close', 'opened'],
		data() {
			return {
				isOpen: false,
			}
		},
		mounted() {
			window.addEventListener('keydown', this.handleEsc);
		},
		beforeUnmount() {
			window.removeEventListener('keydown', this.handleEsc);
		},
		methods: {
			open() {
				this.isOpen = true;
				this.$emit('opened');
			},
			close() {
				this.isOpen = false;
				this.$emit('close');
			},
			onOverlayClick() {
				if (this.outsideClickClose) this.close();
			},
			handleEsc(event) {
				if (this.isOpen && this.escToClose && event.key === 'Escape') this.close();
			},
		},
	}
</script>

<style lang="scss">
	.ck-modal-overlay {
		position: fixed;
		inset: 0;
		z-index: 10001;
		background: rgba(0, 0, 0, .6);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.ck-modal-content {
		position: relative;
		z-index: 10002;
		min-width: 300px;
		max-width: 95dvw;
		max-height: 95dvh;
		overflow: auto;
		background: var(--ck-card-bg, #fff);
		border-radius: .5rem;
		padding: 1.25rem;
	}
</style>
