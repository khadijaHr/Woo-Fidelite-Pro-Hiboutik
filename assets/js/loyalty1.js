jQuery(document).ready(function($) {
    $("#lp_apply_points").on("click", function() {
        var points = $("#lp_points_to_use").val();
        
        $.ajax({
            type: "POST",
            url: ajaxurl, // WP fournit automatiquement cette variable dans l'admin
            data: {
                action: "lp_apply_loyalty_points",
                points: points
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload(); // Recharge la page pour recalculer le sous-total
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    //
    
    // //jQuery(document).ready(function($) {
    //     $(document).on("click", "#lp_remove_points", function() {
    //         $.ajax({
    //             type: "POST",
    //             url: ajaxurl,
    //             data: {
    //                 action: "lp_remove_loyalty_points"
    //             },
    //             success: function(response) {
    //                 if (response.success) {
    //                     location.reload(); // Recharger la page pour recalculer le panier sans les points
    //                 } else {
    //                     alert(response.data.message);
    //                 }
    //             }
    //         });
    //     });
    // //});   

});

//
