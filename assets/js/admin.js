const WPFormsCryptoPay= window.WPFormsCryptoPay || ( function(document, $) {
    const vars = window.wpforms_builder_cryptopay || {};
	const app = {
		init: function() {
			$(document).on('wpformsFieldAdd', app.addField);
			$(document).on('wpformsFieldDelete', app.removeField);
			$(document).on('wpformsSaved', app.totalFieldCheck);
			$(document).on('wpformsFieldAdd', app.totalFieldCheck);
			$(document).on('wpformsSaved', app.paymentsEnabledCheck);
			$(document).on('wpformsFieldAdd', app.paymentsEnabledCheck);
			$(document).on('wpformsFieldAdd', app.hideOrShowSubmitButton);
			$(document).on('wpformsFieldDelete', app.hideOrShowSubmitButton);
			$(document).on('wpformsPanelSwitched', app.hideOrShowSubmitButton);
            if ($(`.wpforms-field.wpforms-field-${ vars.type }:visible`).length) {
                $(".wpforms-field-submit").hide();
            }
		},
        totalFieldCheck: function() {
            if (
                !$(`.wpforms-field.wpforms-field-${ vars.type }:visible`).length ||
                $(`.wpforms-field-payment-total`).length
            ) {
                return;
            }

            $.alert({
                title: wpforms_builder.heads_up,
                content: wpforms_builder.cp_total_field_not_found,
                icon: 'fa fa-exclamation-circle',
                type: 'orange',
                buttons: {
                    confirm: {
                        text: wpforms_builder.ok,
                        btnClass: 'btn-confirm',
                        keys: ['enter'],
                    },
                },
            });
        },
		paymentsEnabledCheck: function() {
			if (
                !$(`.wpforms-field.wpforms-field-${ vars.type }:visible`).length ||
				$('#wpforms-panel-field-'+vars.type+'-enable').is(':checked')
			) {
				return;
			}

			$.alert({
				title: wpforms_builder.heads_up,
				content: wpforms_builder.cp_payments_enabled_required,
				icon: 'fa fa-exclamation-circle',
				type: 'orange',
				buttons: {
					confirm: {
						text: wpforms_builder.ok,
						btnClass: 'btn-confirm',
						keys: ['enter'],
					},
				},
			});
		},
		addField: function(e, id, type) {
			if (vars.type === type) {
				$('#wpforms-add-fields-' + vars.type).prop('disabled', true);
			}
		},
		removeField: function(e, id, type) {
			if (vars.type === type) {
				$('#wpforms-add-fields-' + vars.type).prop('disabled', false);
			}
		},
        hideOrShowSubmitButton: function() {
            if ($(`.wpforms-field.wpforms-field-${ vars.type }:visible`).length) {
                $(".wpforms-field-submit").hide();
            } else {
                $(".wpforms-field-submit").show();
            }
        }
	};
	return app;

}(document, jQuery));

// Initialize.
WPFormsCryptoPay.init();
