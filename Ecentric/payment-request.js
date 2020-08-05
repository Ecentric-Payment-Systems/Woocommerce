jQuery(function ($) {
    var flag = 0;
    if ($('#payment_method_ecentric').is(':checked')) {
        var $form = $('form.woocommerce-checkout');
        $form.on('checkout_place_order', function () {
            if ($('#confirm-order-flag').length == 0 && flag == 0) {
                //console.log($('#confirm-order-flag').length)
                $form.append('<input type="hidden" id="confirm-order-flag" name="confirm-order-flag" value="1">');
            }
            return true;
        });

        $(document.body).on('checkout_error', function () {
            var error_count = $('.woocommerce-error li').length;
            if (error_count === 1) {// Validation Passed (Just the Fake Error I Created Exists)
                $('.woocommerce-NoticeGroup-checkout').css('display', 'none');
                $('#confirm-order-flag').remove();
                flag = 1;
                $('#place_order').click(showGateway());
            } else { // Validation Failed (Real Errors Exists, Remove the Fake One)
                $('.woocommerce-error li').each(function () {
                    var error_text = $(this).text();
                    if (error_text === "Error") {
                        $(this).css('display', 'none');
                    }
                });
            }
        });

        function showGateway() {
            var $form = $('form.woocommerce-checkout');
            //console.log('The ecentric payment method has been checked');
            window.hpp.payment(params, function (data) {
                var post = {'action' : 'my_action', 'TransactionID': data.TransactionID, 'MerchantReference': data.MerchantReference, 'Result': data.Result, 'FailureMessage': data.FailureMessage, 'Amount': data.Amount, 'Checksum': data.Checksum};
                $.post(ajax_object.ajax_url, post, function (response) {
                    if(response === "Success"){
                        $form.append('<input type="hidden" name="TransactionID" value="' + data.TransactionID + '"/>');
                        $form.append('<input type="hidden" name="Amount" value="' + data.Amount + '"/>');
                        $form.append('<input type="hidden" name="MerchantReference" value="' + data.MerchantReference + '"/>');
                        $form.append('<input type="hidden" name="Result" value="' + data.Result + '"/>');
                        $form.append('<input type="hidden" name="FailureMessage" value="' + data.FailureMessage + '"/>');
                        $form.append('<input type="hidden" name="Checksum" value="' + data.Checksum + '"/>');
                        //$form.append( '<input type="hidden" name="OrderID" value="' + order_id + '"/>' );
                        $form.submit();
                        return true;
                    } else {
                        //console.log('Invalid checksum');
                        return false;
                    }
                });
               // if (sha256.validate(params.Key, data.TransactionID, data.MerchantReference, data.Result, data.FailureMessage, data.Amount, data.Checksum)) {
            }, function (e) {
                //console.log("Cancel");
                window.location.reload();
                //console.log(e);
                return false;
            });
            //console.log('Hit final');
            return false;
        }
    }
});