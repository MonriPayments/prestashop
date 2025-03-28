$(document).ready(function() {
    function toggleInstallments() {
        console.log('val: ', $('input[name="MONRI_INSTALLMENTS"]:checked').val())
        if ($('input[name="MONRI_INSTALLMENTS"]:checked').val() === 'MONRI_INSTALLMENTS_YES') {
            $('.monri_installments_count').show();
        } else {
            $('.monri_installments_count').hide();
        }
    }

    toggleInstallments();

    $('input[name="MONRI_INSTALLMENTS"]').change(function() {
        toggleInstallments();
    });
});
