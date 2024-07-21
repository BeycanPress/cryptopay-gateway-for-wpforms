(($) => {
    $(document).ready(() => {
        const helpers = window.cpHelpers || window.cplHelpers
        const app = window.CryptoPayApp || window.CryptoPayLiteApp
        const { 
            type,
            amountMustBeGreaterThanZero,
            paymentCompletedMessage
        } = window.wpforms_cryptopay

        const order = {}
        const params = {}

        let startedApp;
        let currentForm;

        app.events.add('init', (ctx) => {
            if (!currentForm.valid()) {
                ctx.error = true
            }
        })

        const transactionInput = (transaction) => {
            return `<input type="hidden" name="wpforms[transaction-hash]" value="${transaction.id}" />`
        }

        app.events.add('confirmationCompleted', async (ctx) => {
            ctx.disablePopup = true;
            $('.overlay').remove();
            $('#' + type).remove();
            $(".wpforms-field-" + type).remove();
            currentForm.append(transactionInput(ctx.transaction))
            helpers.successPopup(paymentCompletedMessage)
            currentForm.find('.wpforms-submit').removeClass('wpforms-hidden')
            currentForm.submit();
        })
        
        $(document).on('wpformsAmountTotalCalculated', (e, form, total) => {
            currentForm = form
            params.formId = form.data('formid')
            order.currency = wpforms.getCurrency().code
            order.amount = parseFloat(wpforms.amountSanitize(total))

            if (order.amount) {
                if (startedApp) {
                    startedApp.reStart(order, params)
                } else {
                    startedApp = app.start(order, params)
                }
                $(".wpforms-error.cp-error").remove()
            } else if ($(".wpforms-error.cp-error").length === 0) {
                $("#" + type).before('<div class="wpforms-error wpforms-error-generic cp-error">' + amountMustBeGreaterThanZero + '</div>')
            }
        });
    });
})(jQuery);