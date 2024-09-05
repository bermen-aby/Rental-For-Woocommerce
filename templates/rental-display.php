<?php
global $product;

$rental_option = get_post_meta($product->get_id(), '_rental_option', true);
$rental_price_day = get_post_meta($product->get_id(), '_rental_price_day', true);
$rental_price_week = get_post_meta($product->get_id(), '_rental_price_week', true);
$rental_price_month = get_post_meta($product->get_id(), '_rental_price_month', true);
$rental_description = get_post_meta($product->get_id(), '_rental_description', true);

// Récupération des locations déjà enregistrées pour ce produit
$booked_rentals = wc_rental_get_booked_rentals($product->get_id());

$today = new DateTime();

// Date de début par défaut = demain
$start_date = $today->modify('+1 day')->format('Y-m-d');

// Date de fin par défaut = après-demain
$end_date = $today->modify('+1 day')->format('Y-m-d');

// Calcul du prix total par défaut
$totalPrice = calculateRentalPrice($start_date, $end_date, $rental_price_day, $rental_price_week, $rental_price_month);

function calculateRentalPrice($startDate, $endDate, $rentalPriceDay, $rentalPriceWeek, $rentalPriceMonth)
{
    $startDate = new DateTime($startDate);
    $endDate = new DateTime($endDate);
    $timeDiff = $endDate->getTimestamp() - $startDate->getTimestamp();
    $diffDays = ceil($timeDiff / (1 * 24 * 60 * 60));

    $totalPrice = 0;
    if ($diffDays <= 7) {
        $totalPrice = $diffDays * $rentalPriceDay;
    } elseif ($diffDays <= 30) {
        $totalPrice = (floor($diffDays / 7) * $rentalPriceWeek) + (($diffDays % 7) * $rentalPriceDay);
    } else {
        $totalPrice = (floor($diffDays / 30) * $rentalPriceMonth) + (($diffDays % 30) * $rentalPriceDay);
    }

    return $totalPrice;
}
?>

<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'rental-display.css'; ?>">

<div class="rental-section card">
    <div class="card-header">
        <h3><?php _e('Location', 'wc-rental'); ?></h3>
    </div>
    <div class="card-body">
        <div class="rental-description">
            <?php echo apply_filters('the_content', get_post_meta($product->get_id(), '_rental_description', true)); ?>
        </div>
        <form class="cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype='multipart/form-data'>
            <div class="rental-dates">
                <div class="form-group">
                    <label for="rental_start_date"><?php _e('Date de début', 'wc-rental'); ?></label>
                    <input type="date" id="rental_start_date" name="rental_start_date" value="<?php echo $start_date; ?>" required>
                </div>
                <div class="form-group">
                    <label for="rental_end_date"><?php _e('Date de fin', 'wc-rental'); ?></label>
                    <input type="date" id="rental_end_date" name="rental_end_date" value="<?php echo $end_date; ?>" required>
                </div>
            </div>
            <table class="rental-price">
                <tr>
                    <td><?php _e('Prix par jour:', 'wc-rental'); ?></td>
                    <td class="price"><?php echo wc_price(wmc_get_price($rental_price_day)); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Prix par semaine:', 'wc-rental'); ?></td>
                    <td class="price"><?php echo wc_price(wmc_get_price($rental_price_week)); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Prix par mois:', 'wc-rental'); ?></td>
                    <td class="price"><?php echo wc_price(wmc_get_price($rental_price_month)); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Prix total:', 'wc-rental'); ?></td>
                    <td class="price" id="rental-total-price"><?php echo wc_price(wmc_get_price($totalPrice)); ?></td>
                </tr>
            </table>
            <div class="rental-actions">
                <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt">
                    <?php
                    if ($rental_option == 'rental' || $rental_option == 'both') {
                        echo esc_html__('Louer', 'wc-rental');
                    } else {
                        echo esc_html__('Acheter', 'wc-rental');
                    }
                    ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    var rentalPriceDay = <?php echo $rental_price_day; ?>;
    var rentalPriceWeek = <?php echo $rental_price_week; ?>;
    var rentalPriceMonth = <?php echo $rental_price_month; ?>;

    function calculateRentalPrice(startDate, endDate) {
        var startDate = new Date(startDate);
        var endDate = new Date(endDate);
        var timeDiff = Math.abs(endDate.getTime() - startDate.getTime());
        var diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

        var totalPrice = 0;
        if (diffDays <= 7) {
            totalPrice = diffDays * rentalPriceDay;
        } else if (diffDays <= 30) {
            totalPrice = (Math.floor(diffDays / 7) * rentalPriceWeek) + ((diffDays % 7) * rentalPriceDay);
        } else {
            totalPrice = (Math.floor(diffDays / 30) * rentalPriceMonth) + ((diffDays % 30) * rentalPriceDay);
        }

        return totalPrice;
    }

    $('#rental_start_date, #rental_end_date').change(function() {
        var startDate = new Date($('#rental_start_date').val());
        var endDate = new Date($('#rental_end_date').val());

        if (endDate <= startDate) {
            alert('La date de fin doit être supérieure à la date de début.');
            $(this).val('');
            return;
        }

        var totalPrice = calculateRentalPrice($('#rental_start_date').val(), $('#rental_end_date').val());
        $('#rental-total-price').html(totalPrice.toFixed(2) + ' <?php echo get_woocommerce_currency_symbol(); ?>');
    });

    // Ouverture directe du calendrier en cliquant sur les sélecteurs de date
    $('#rental_start_date, #rental_end_date').datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0
    });
</script>