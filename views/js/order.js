jQuery(document).ready(function () {
    var MonriConfig = window.MonriConfig
    if (typeof MonriConfig == "undefined") {
        return
    }
    var authenticityToken = MonriConfig.authenticityToken;
    var clientSecret = MonriConfig.clientSecret;
    var monri = Monri(authenticityToken);
    var components = monri.components({"clientSecret": clientSecret});
    // Create an instance of the card Component.
    var card = components.create('card');
    card.mount('card-element-' + clientSecret);

    $(".monri-payment-form-" + clientSecret).on('submit', function (e) {
        e.preventDefault();
        console.log("Prevented form submit")
        monri.confirmPayment(card, {}).then(function (result) {
            alert(result.result.status)
        })
    })
})