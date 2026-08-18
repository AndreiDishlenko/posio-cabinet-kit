import { ref } from 'vue';

class PauseApplicationClass {
    initiators = [];
    inprogress

    constructor() {
        this.initiators = []
        this.inprogress = ref(false)
        this.modal_inprogress = ref(false)
    }

    // Шину событий передаёт приложение: у пакета нет собственного синглтона шины.
    init(emitter) {
        // Инициатор приходит вместе с событием: общий ключ на всех означал, что
        // первый ответивший из пачки параллельных запросов разблокирует экран,
        // пока остальные ещё висят.
        emitter?.on('pause_application',     (initiator) => { this.pause(initiator ?? 'emitter') })
        emitter?.on('unpause_application',   (initiator) => { this.unpause(initiator ?? 'emitter') })

        return this.inprogress;
    }

    pause(initiator, reason='') {
        if ( !this.initiators.includes(initiator) )
            this.initiators.push(initiator);

        this.inprogress.value=true;
        this.modal_inprogress.value=true;
    }

    unpause(initiator, reason='') {
        if ( this.initiators.includes(initiator) )
            this.initiators.splice(this.initiators.indexOf(initiator), 1);

        if (this.initiators.length)
            return;

        this.inprogress.value=false;
        this.modal_inprogress.value=false;
    }

}

const $pauseApplication = new PauseApplicationClass();

const $inprogress = $pauseApplication.inprogress;
const $modal_inprogress = $pauseApplication.modal_inprogress;

export { $inprogress, $modal_inprogress, $pauseApplication };
