jQuery(function ($) {
    'use strict';

    $(document).ready(function () {
        $('.krokedil-support-form input.button').on('click', function () {
            window.onbeforeunload = null;
        });


        $('#system-report').val(function () {
            /* Refer to "wp-content/plugins/woocommerce/assets/js/admin/system-status.js:generateReport()" */
            let report = '';
            $('.wc_status_table thead, .wc_status_table tbody').each(function () {
                if ($(this).is('thead')) {
                    var label = $(this).find('th:eq(0)').data('exportLabel') || $(this).text();
                    report = report + '\n### ' + label.trim() + ' ###\n\n';
                } else {
                    $('tr', $(this)).each(function () {
                        var label = $(this).find('td:eq(0)').data('exportLabel') || $(this).find('td:eq(0)').text();
                        var the_name = label.trim().replace(/(<([^>]+)>)/ig, ''); // Remove HTML.
                        // Find value
                        var $value_html = $(this).find('td:eq(2)').clone();
                        $value_html.find('.private').remove();
                        $value_html.find('.dashicons-yes').replaceWith('&#10004;');
                        $value_html.find('.dashicons-no-alt, .dashicons-warning').replaceWith('&#10060;');
                        // Format value
                        var the_value = $value_html.text().trim();
                        var value_array = the_value.split(', ');
                        if (value_array.length > 1) {
                            // If value have a list of plugins ','.
                            // Split to add new line.
                            var temp_line = '';
                            $.each(value_array, function (key, line) {
                                temp_line = temp_line + line + '\n';
                            });
                            the_value = temp_line;
                        }
                        report = report + '' + the_name + ': ' + the_value + '\n';
                    });
                }

            })

            return report;
        });

        $('.system-report-wrapper a').on('click', function () {
            $('.system-report-content').val($('#system-report').val());
        });

        $('.system-report-action').click(function (e) {
            $('.system-report-content').toggle({ duration: 250 });
            if ($(this).text() === 'View report') {
                $(this).text('Hide report');
            } else {
                $(this).text('View report');
            }
            e.preventDefault();
        })

        $('.view-log').click(function (e) {
            $(this).siblings('.log-content').toggle({ duration: 250 });
            if ($(this).text() === 'View log') {
                $(this).text('Hide log');
            } else {
                $(this).text('View log');
            }
            e.preventDefault();
        })

        for (let i = 1; i <= 5; i++) {
            $('select.additional-log-' + i).removeAttr('name');
        }

        $('a.additional-log').click(function (e) {
            $(this).hide();
            $(this).siblings('.additional-log-1').show();
            $('select.additional-log-1').attr('name', 'additional-log-1');

            for (let i = 1; i < 5; i++) {
                $(this).siblings('a.additional-log-' + i).click(function (e) {
                    /* Show the <div> and the next <a>: */
                    const siblings = $(this).siblings('.additional-log-' + (i + 1));
                    siblings.show();

                    const div = siblings.first('div');
                    div.children('select').first().attr('name', 'additional-log-' + (i + 1));
                    div.css('display', 'flex');

                    $(this).hide();
                    e.preventDefault();
                });
            }
        })

        $('.woocommerce-log .view-log').on('click', function () {
            const pathname = $(this).siblings('select').val();
            if (pathname.length === 0) {
                return;
            }

            const textarea = $(this).siblings('textarea');

            $.ajax({
                type: 'GET',
                url: krokedil_support_form_params.get_plugin_log_content_url,
                data: {
                    filename: pathname,
                    nonce: krokedil_support_form_params.get_plugin_log_content_nonce,
                },
                dataType: 'JSON',
                cache: true,
                success: function (response) {
                    console.log(response)
                    textarea.val(response.data);
                },
                error: function (response) {
                    console.error(response);
                }
            })

        });

        $('#krokedil-support-form input[type=submit]').on('click', function () {
            $('#krokedil-support-form').submit();
        });
    });
});