jQuery(document).ready(function () {
    var MonriConfig = window.MonriConfig
    if (typeof MonriConfig == "undefined") {
        return
    }
    var $ = jQuery;
    var cardData = {};
    var authenticityToken = MonriConfig.authenticityToken;
    var clientSecret = MonriConfig.clientSecret;
    var monri = Monri(authenticityToken);
    var components = monri.components({"clientSecret": clientSecret});
    // Create an instance of the card Component.
    var card = components.create('card');
    card.mount('card-element-' + clientSecret);

    var $cardDiscount = $("#card-discount");
    var $fee = $("#wk-payment-fee");

    card.addChangeListener('card_number', function (event) {
        cardData = event.data;
        // 1. enter bin Y
        // 2. wait for discount info, is there a way to assert we actually got info Y
        // 4. show discount message - if discount is available Y
        // 5. hide wk fee - if discount is available Y
        // 6. show wk fee if discount is not available Y
        // 7. hide discount message if discount is not available Y
        // 8. update price somehow?
        if (cardData.discount) {
            $fee.hide();
            $cardDiscount.html(cardData.discount.message);
        } else {
            $fee.show();
            $cardDiscount.html('');
            $cardDiscount.hide();
        }
        fetchPrice(cardData, function (price) {
            console.log("Received price", price);
        })
        console.log(cardData)
    });

    function fetchPrice(cardData, callback) {
        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            url: MonriConfig.calculatePriceEndpoint,
            data: {
                method: 'test',
                ajax: true,
                card_data: cardData
                // token: token
            },
            success: function (data) {
                callback(data)
            }
        });
    }

    var form = $(".monri-payment-form-" + clientSecret);
    var preventDefault = true;
    form.on('submit', function (e) {
        if (!preventDefault) {
            return;
        }
        e.preventDefault();
        var priceCardData = {
            "bin": cardData.bin,
            "brand": cardData.brand,
            "cc_issuer": cardData.cc_issuer,
            "discount": cardData.discount
        }

        if (!priceCardData.discount) {
            delete priceCardData.discount;
        }

        fetchPrice(cardData, function (price) {
            console.log(price);
            // monri.confirmPayment(card, {}).then(function (result) {
            //     console.log(result);
            //     if (result.error) {
            //         alert("Transakcija odbijena, probajte ponovo")
            //     } else {
            //         var paymentResult = result.result;
            //         if (paymentResult.status != "approved") {
            //             alert("Transakcija odbijena, probajte ponovo")
            //         } else {
            //             preventDefault = false;
            //             $("input[name=monri-order-number]").val(paymentResult.order_number)
            //             $("input[name=monri-amount]").val(paymentResult.amount)
            //             form.submit();
            //         }
            //     }
            // })
        })

    })
})