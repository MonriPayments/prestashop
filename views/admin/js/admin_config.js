const allowed_payment_methods = ['monri_webpay', 'monri_wspay'];

function toggleInstallments() {
    const selectedPaymentMethod = $('input[name="MONRI_PAYMENT_GATEWAY_SERVICE_TYPE"]:checked').val();
    const isInstallmentsEnabled = $('input[name="MONRI_INSTALLMENTS"]:checked').val() === 'MONRI_INSTALLMENTS_YES';

    console.log('Selected Payment Method:', selectedPaymentMethod);
    console.log('Installments Enabled:', isInstallmentsEnabled);
    //monri components does allow installments but we have no way of setting the maximum number of installments
    if (isInstallmentsEnabled && allowed_payment_methods.includes(selectedPaymentMethod)) {
        $('.monri_installments_count').show();
    } else {
        $('.monri_installments_count').hide();
    }
}

// Run on page load
$(document).ready(function() {
    toggleInstallments();

    // Listen for changes in installment selection
    $('input[name="MONRI_INSTALLMENTS"], input[name="MONRI_PAYMENT_GATEWAY_SERVICE_TYPE"]').change(function() {
        toggleInstallments();
    });
});
