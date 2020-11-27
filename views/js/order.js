jQuery(document).ready(function () {
    var MonriConfig = window.MonriConfig
    if (typeof MonriConfig == "undefined") {
        return
    }
    var $ = jQuery;
    var cardData = {};
    var authenticityToken = MonriConfig.authenticityToken;
    var clientSecret = MonriConfig.clientSecret;
    var monri = Monri(authenticityToken, {
        locale: 'hr'
    });
    var components = monri.components({"clientSecret": clientSecret});
    // Create an instance of the card Component.
    var card = components.create('card', {
        style: {
            invalid: {
                color: 'red'
            },
            complete: {
                color: '#419E41'
            },
            label: {
                base: {
                    color: 'blue',
                    textTransform: 'none'
                },
                invalid: {
                    color: 'gray'
                },
                complete: {
                    color: '#419E41'
                }
            },
            input: {
                base: {
                    color: "#419E41"
                }
            }
        },
    });
    card.mount('card-element-' + clientSecret);

    var $cardDiscount = $("#card-discount");
    var activeDiscount;

    var cartTotalObject = $('#js-checkout-summary .cart-summary-line.cart-total span.value');

    var memoizeBinResult = {};
    var lastAmount = '';

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
            $cardDiscount.html(cardData.discount.message);
            $cardDiscount.show();
        } else {
            $cardDiscount.html('');
            $cardDiscount.hide();
        }

        activeDiscount = cardData.discount || null;

        fetchPrice(cardData, function (result) {
            if (payUsingMonriActive) {
                lastAmount = result['amount'];
                cartTotalObject.html(lastAmount);
            }
        })
    });

    var cardChangeError;

    card.onChange(function(event) {
        var displayError = document.getElementById('card-errors');
        cardChangeError = event.error;
        if (event.error) {
            displayError.textContent = event.error.message;
            $("#card-errors-discounts-hr").show();
        } else {
            displayError.textContent = '';
            $("#card-errors-discounts-hr").hide();
        }
    });

    function resetPrice(cardData, callback, err) {
        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            url: MonriConfig.resetPriceEndpoint,
            data: {
                action: 'reset'
            },
            success: function (data) {
                callback(data)
            },
            error: function (data) {
                if (typeof err === "function") {
                    err(data);
                }
            }
        });
    }

    function updatePrice(cardData, callback, err) {
        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            url: MonriConfig.calculatePriceEndpoint,
            data: {
                ajax: true,
                action: 'update',
                card_data: cardData,
                client_secret: clientSecret,
                token: staticToken
            },
            success: function (data) {
                callback(data)
            },
            error: function (data) {
                if (typeof err === "function") {
                    err(data);
                }
            }
        });
    }

    function fetchPrice(cardData, callback, err) {
        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            url: MonriConfig.calculatePriceEndpoint,
            data: {
                method: 'test',
                ajax: true,
                action: 'price',
                card_data: cardData,
                client_secret: clientSecret,
                token: staticToken
            },
            success: function (data) {
                callback(data)
            },
            error: function (data) {
                if (typeof err === "function") {
                    err(data);
                }
            }
        });
    }

    function isMonriModuleSelected() {
        var moduleId = $('.payment-option input[name="payment-option"]:checked').attr('id');
        var monriOrderNumber = $('#' + "pay-with-" + moduleId + "-form").find('input[name=monri-order-number]');
        return monriOrderNumber.length > 0
    }

    var form = $(".monri-payment-form-" + clientSecret);
    var preventDefault = true;
    var payUsingMonriActive = isMonriModuleSelected();

    $('.payment-option').on('click', function () {
        payUsingMonriActive = isMonriModuleSelected();
        if (payUsingMonriActive && lastAmount !== '') {
            cartTotalObject.html(lastAmount);
        }
    });

    form.on('submit', function (e) {
        if (!preventDefault) {
            return;
        }
        e.preventDefault();

        if(cardChangeError != null) {
            alert(cardChangeError.message)
            return;
        }

        var priceCardData = {
            "bin": cardData.bin,
            "brand": cardData.brand,
            "cc_issuer": cardData.cc_issuer,
            "discount": cardData.discount
        }

        if (!priceCardData.discount) {
            delete priceCardData.discount;
        }

        updatePrice(cardData, function (price) {
            monri.confirmPayment(card, {
                customParams: JSON.stringify({
                    custom_products: price.custom_params_products
                })
            }).then(function (result) {
                if (result.error) {
                    alert("Transakcija odbijena, pokušajte ponovo")
                } else {
                    var paymentResult = result.result;
                    if (paymentResult.status !== "approved") {
                        resetPrice(cardData, function () {
                            alert(paymentResult.message)
                        })
                    } else {
                        preventDefault = false;
                        $("input[name=monri-order-number]").val(paymentResult.order_number)
                        $("input[name=monri-amount]").val(paymentResult.amount)
                        form.submit();
                    }
                }
            })
        },function (err) {
            alert("Desila se greška prilikom procesiranja zahtjeva, molimo pokušajte ponovo")
        })

    })
})