jQuery(document).ready(function($) {
    $('.edit-button').on('click', function(e) {
        e.preventDefault(); // Empêche l'action par défaut du lien
        
        var orderId = $(this).data('order-id');
        var productPrice = $(this).data('product-price');
        var lineItemId = $(this).data('line-item-id');
        var nonce = $(this).data('nonce');

        // Action AJAX avec l'URL correcte
        $.ajax({
            url: lp_edit_order_vars.ajax_url, // Correction ici
            method: 'GET',
            data: {
                action: 'lp_edit_order',
                order_id: orderId,
                product_price: productPrice,
                line_item_id: lineItemId,
                _wpnonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert('Erreur : ' + response.data.message);
                }
            },
            error: function() {
                alert('Erreur lors de la modification.');
            }
        });
    });
});
