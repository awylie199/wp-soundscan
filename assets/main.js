import './main.scss';
import moment from 'moment'

(function($) {
    $(function() {
        let dateURL = $('#wss-dates').data('url'),
            $spinner = $('#wss-dates-spinner'),
            $errorOutput = $('#wss-dates-error'),
            $output = $('#wss-menu');
        
        $('.datepicker').datepicker({
            onSelect() {
                let $to = $('#wss-to'),
                    $from = $('#wss-from'),
                    to = moment($to.val(), 'YYYYMMDD'),
                    from = moment($from.val(), 'YYYYMMDD');
                
                if (to.isValid() && from.isValid()) {
                    let request = $.ajax({
                            data: {
                                to: to.format('YYYYMMDD'),
                                from: from.format('YYYYMMDD')
                            },
                            dataType: 'json'
                        }),
                        minTimerPromise = new Promise(function(resolve) {
                            $spinner.show();
                            
                           window.setTimeout(function() {
                               $spinner.hide();
                               resolve();
                           }, 1500) 
                        });
                    
                    $errorOutput.hide();
                    
                    Promise.all([request, minTimerPromise]).then(function([response]) {
                        if (response.data.hasOwnProperty('success') &&
                            data.success === true && response.data.hasOwnProperty('data')) {
                            $output.html(response.data);
                        } else {
                            throw new Error();
                        }
                    }).catch(function() {
                        $errorOutput.show();
                    });
                }
            }
        });
    });
})(jQuery);

