// import Vue3Toastify from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { toast } from 'vue3-toastify';

import { $t } from '@/js/i18n.config'

class ToastClass {
    
    constructor() {
        this.settings = {
            theme: "colored",
            autoClose: 3000,
            position: toast.POSITION.BOTTOM_CENTER,
            // transition: toast.TRANSITIONS.FLIP,
            transition: toast.TRANSITIONS.BOUNCE,
            // toastClassName: 'toast-classssssss2222',
            // bodyClassName: 'toast-body-Ccccct-size222',
            // progressClassName: 'fancy-progress-bar222',

            // style: {
            //     opacity: '1',
            //     userSelect: 'initial',
            // },
            pauseOnFocusLoss: false,
            hideProgressBar: true
        }
    }

    success(message) {
        // console.log('success', message); 
        message = $t(message)

        toast.success(message, {
            ...this.settings,
            toastStyle: {
                backgroundColor: 'var(--success-color)',
                // fontSize: '10px',
            },
        });
    }

    error(message) {
        // console.log('success', message);   
        message = $t(message)
        
        toast.error(message, {
            ...this.settings,
            toastStyle: {
                backgroundColor: 'var(--error-color)',
                // fontSize: '10px',
            },
        });
    }

    info(message, options = {}) {
        message = $t(message)

        toast.info(message, {
            ...this.settings,
            autoClose: options.autoClose ?? 6000,
            ...options,
        });
    }

    install(app, options) {
        app.config.globalProperties.$toast = this;
        app.config.globalProperties.$Toast = this;
    }
}

const Toast = new ToastClass()

export { Toast }