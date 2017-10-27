import './main.scss';
import moment from 'moment';

(function($) {
    $(function() {
        let $dates = $('#wss-dates'),
            url = $dates.data('url'),
            jqueryDateFormat = $dates.data('jquery-date-format'),
            momentDateFormat = $dates.data('moment-date-format'),
            $spinner = $('#wss-dates-spinner'),
            $errorOutput = $('#wss-dates-error'),
            $output = $('#wss-menu');

        $('.datepicker').datepicker({
            maxDate: moment().toDate(),
            dateFormat: jqueryDateFormat,
            onSelect() {
                let $to = $('#wss-to'),
                    $from = $('#wss-from'),
                    to = moment($to.val(), momentDateFormat),
                    from = moment($from.val(), momentDateFormat);

                if (to.isValid() && from.isValid()) {
                    if (from.isBefore(to) || +to.diff(from, 'days') === 0) {
                        let request = $.ajax(url, {
                                method: 'GET',
                                data: {
                                    to: to.format('YYYYMMDD'),
                                    from: from.format('YYYYMMDD')
                                },
                                dataType: 'json'
                            }),
                            minTimerPromise = new Promise(function(resolve) {
                                $spinner.css({visibility: 'visible'});

                                window.setTimeout(function() {
                                    $spinner.css({visibility: 'hidden'});
                                    resolve();
                                }, 1500);
                            });

                        $errorOutput.css({visibility: 'hidden'});
                        $errorOutput.children().hide();

                        Promise.all([request, minTimerPromise]).then(function([response]) {
                            if (response.hasOwnProperty('success') &&
                                    response.success === true &&
                                    response.hasOwnProperty('data')) {
                                $output.html(response.data);
                            } else {
                                throw new Error();
                            }
                        }).catch(function() {
                            $errorOutput.css({visibility: 'visible'});
                            $errorOutput.children('.wss-dates-error__server').show();
                            $errorOutput.children('.wss-dates-error__dates').hide();
                        });
                    } else {
                        $errorOutput.css({visibility: 'visible'});
                        $errorOutput.children('.wss-dates-error__dates').show();
                        $errorOutput.children('.wss-dates-error__server').hide();
                    }
                } else {
                    $errorOutput.css({visibility: 'visible'});
                    $errorOutput.children('.wss-dates-error__dates').show();
                    $errorOutput.children('.wss-dates-error__server').hide();
                }
            }
        });
    });
})(jQuery);

