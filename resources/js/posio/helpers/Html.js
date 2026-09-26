export const Html = {
    getInputValue: function(input) {
        // console.log('getInputValue', input.attr('field'), input.attr('type'), input);
        if (!input)
            return;
    
        if (input.attr('type') === 'checkbox' || input.attr('type') === 'radio') {
            // console.log('getInputValue', input.is(":checked"));
            return input.is(":checked") ? true : false;
        }
        
        return input.val();
    },
    
    setInputValue: function(input, val) {
        // console.log('$H.html.setInputValue ', val);
        // console.log(input);
        // $(input).val(val);
        // input.value = val;
        // console.log(input.val());
    
        if (input.attr('type')=='date') {
            // console.log('setInputValue', val);
            var d = new Date();
            if (val) d = new Date(val);
            var day = d.getDate();
            var month = d.getMonth() + 1;
            var year = d.getFullYear();
            if (day < 10) day = "0" + day;
            if (month < 10) month = "0" + month;
    
            input.val( year + "-" + month + "-" + day);
            return;
        }
        if (input.attr('type')=='password') {
            input.val('');
            return;
        }
        if (input.attr('type')=='checkbox' || input.attr('type')=='radio') {
            val==1 ? input.prop('checked', true) : input.prop('checked', false);
            return;
        }
        if ( input.is('select') ) {
            input.find('option').prop('selected', false);
            input.find('option[value="'+val+'"]').prop('selected', true);
            return;
        }
        input.val(val);
    },
    
    enableButton: function(button, state) {
        // console.log('$H.html.enableButton '+state);
        if (state) {
            button.attr('disabled', false);
            button.removeClass('btn-secondary');
        } else {
            button.attr('disabled', true);
            button.addClass('btn-secondary');
        }
    }
}

// Window.$H.html = Html;