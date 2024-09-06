<?php
/*
Plugin Name: Premium Group Rental For WooCommerce
Description: Ajoute la possibilité de louer des produits sur votre boutique WooCommerce
Version: 1.1
Author: Bermen
*/

// Inclure le plugin Cars Manager
require_once plugin_dir_path(__FILE__) . 'premium-group-cars-manager.php';
require_once plugin_dir_path(__FILE__) . 'premium-group-spare-parts-manager.php';

register_activation_hook(__FILE__, 'wc_rental_activate');

function wc_rental_activate()
{
    wc_rental_create_table();
    wc_rental_register_taxonomies();
    flush_rewrite_rules();
}

function wc_rental_create_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . "rental_bookings";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        product_id mediumint(9) NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,
        customer_id mediumint(9) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function create_quotations_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quotations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        nom_piece text NOT NULL,
        numero_chassis text,
        marque text,
        modele text,
        sous_modele text,
        generation text,
        annee text,
        autres_informations text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'create_quotations_table');

function wc_rental_register_taxonomies()
{
    register_taxonomy(
        'rental_option',
        'product',
        array(
            'label' => __('Option de location/vente', 'wc-rental'),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'rental-option'),
        )
    );

    // Ajout des termes par défaut
    wp_insert_term('Vente uniquement', 'rental_option', array('slug' => 'sale'));
    wp_insert_term('Location uniquement', 'rental_option', array('slug' => 'rental'));
    wp_insert_term('Location et vente', 'rental_option', array('slug' => 'both'));
}

add_action('init', 'wc_rental_register_taxonomies');

// Onglet "Rental"
add_action('woocommerce_product_options_general_product_data', 'wc_rental_product_options');
function wc_rental_product_options()
{
    global $woocommerce, $post;

    echo '<div class="options_group rental-options">';

    // Option de location/vente
    $rental_options = get_terms(array(
        'taxonomy' => 'rental_option',
        'hide_empty' => false,
    ));

    $options = array();
    foreach ($rental_options as $option) {
        $options[$option->slug] = $option->name;
    }

    woocommerce_wp_select(
        array(
            'id' => 'rental_option',
            'label' => __('Option de location/vente', 'wc-rental'),
            'options' => $options,
            'value' => wc_rental_get_product_rental_option($post->ID),
        )
    );

    // Les autres champs restent inchangés
    woocommerce_wp_textarea_input(
        array(
            'id' => '_rental_description',
            'label' => __('Description de location', 'wc-rental'),
            'placeholder' => __('Description de location', 'wc-rental'),
            'class' => 'widefat editor-options'
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => '_rental_price_day',
            'label' => __('Prix par jour', 'wc-rental'),
            'placeholder' => __('Prix par jour', 'wc-rental'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min' => '0'
            )
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => '_rental_price_week',
            'label' => __('Prix par semaine', 'wc-rental'),
            'placeholder' => __('Prix par semaine', 'wc-rental'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min' => '0'
            )
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => '_rental_price_month',
            'label' => __('Prix par mois', 'wc-rental'),
            'placeholder' => __('Prix par mois', 'wc-rental'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min' => '0'
            )
        )
    );

    echo '</div>';
}

// Sauvegarde des données du produit
add_action('woocommerce_process_product_meta', 'wc_rental_product_save');
function wc_rental_product_save($post_id)
{
    if (isset($_POST['rental_option'])) {
        wp_set_object_terms($post_id, $_POST['rental_option'], 'rental_option');
    }

    update_post_meta($post_id, '_rental_description', wpautop(wptexturize($_POST['_rental_description'])));
    update_post_meta($post_id, '_rental_price_day', $_POST['_rental_price_day']);
    update_post_meta($post_id, '_rental_price_week', $_POST['_rental_price_week']);
    update_post_meta($post_id, '_rental_price_month', $_POST['_rental_price_month']);
}

function wc_rental_get_product_rental_option($product_id)
{
    $terms = get_the_terms($product_id, 'rental_option');
    if ($terms && !is_wp_error($terms)) {
        return $terms[0]->slug;
    }
    return 'sale'; // Default to 'sale' if no option is set
}

// Ajout de la section de location sur la page produit
add_action('woocommerce_single_product_summary', 'wc_rental_product_section', 31);
function wc_rental_product_section()
{
    global $product;

    $rental_option = wc_rental_get_product_rental_option($product->get_id());
    if ($rental_option != 'sale') {
        include('templates/rental-display.php');
    }
}


function wc_rental_get_booked_rentals($product_id)
{
    global $wpdb;

    $table_name = $wpdb->prefix . "rental_bookings";

    $booked_rentals = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name WHERE product_id = %d", $product_id),
        OBJECT
    );

    return $booked_rentals;
}


// Hook to modify cart item data when a product is added to the cart
add_filter('woocommerce_add_cart_item_data', 'add_rental_data_to_cart', 10, 2);

function add_rental_data_to_cart($cart_item_data, $product_id)
{
    $rental_option = get_post_meta($product_id, '_rental_option', true);
    if ($rental_option != 'sale' && isset($_POST['rental_start_date']) && isset($_POST['rental_end_date'])) {
        $start_date = DateTime::createFromFormat('Y-m-d', sanitize_text_field($_POST['rental_start_date']));
        $end_date = DateTime::createFromFormat('Y-m-d', sanitize_text_field($_POST['rental_end_date']));
        $days_diff = $end_date->diff($start_date)->days;

        $daily_price = get_post_meta($product_id, '_rental_price_day', true);
        $rental_price = $daily_price * $days_diff;

        $cart_item_data['rental_start_date'] = $start_date->format('Y-m-d');
        $cart_item_data['rental_end_date'] = $end_date->format('Y-m-d');
        $cart_item_data['rental_price'] = $rental_price;
        $cart_item_data['unique_key'] = md5(microtime() . rand());
    }
    return $cart_item_data;
}

// Ajoutez ces nouvelles fonctions
add_action('woocommerce_before_calculate_totals', 'adjust_cart_item_price');

function adjust_cart_item_price($cart_object)
{
    if (!WC()->session->__isset("reload_checkout")) {
        foreach ($cart_object->get_cart() as $cart_item) {
            if (isset($cart_item['rental_price'])) {
                $cart_item['data']->set_price($cart_item['rental_price']);
            }
        }
    }
}

add_filter('woocommerce_get_item_data', 'display_rental_info_cart', 10, 2);

function display_rental_info_cart($item_data, $cart_item)
{
    if (isset($cart_item['rental_start_date']) && isset($cart_item['rental_end_date'])) {
        $item_data[] = array(
            'name' => __('Location', 'wc-rental'), // Added 'Location' text
            'value' => '', // Empty value for the 'Location' label
        );
        $item_data[] = array(
            'name' => __('Date de début', 'wc-rental'),
            'value' => $cart_item['rental_start_date'],
        );
        $item_data[] = array(
            'name' => __('Date de fin', 'wc-rental'),
            'value' => $cart_item['rental_end_date'],
        );
    }

    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'add_rental_data_to_order', 10, 4);

function add_rental_data_to_order($item, $cart_item_key, $values, $order)
{
    if (isset($values['rental_start_date']) && isset($values['rental_end_date'])) {
        $item->add_meta_data(__('Rental Start Date', 'wc-rental'), $values['rental_start_date'], true);
        $item->add_meta_data(__('Rental End Date', 'wc-rental'), $values['rental_end_date'], true);
    }
}

// Modifier le texte du bouton pour les produits en location
add_filter('woocommerce_product_single_add_to_cart_text', 'wc_rental_custom_button_text', 10, 2);
add_filter('woocommerce_product_add_to_cart_text', 'wc_rental_custom_button_text', 10, 2);

function wc_rental_custom_button_text($button_text, $product)
{
    return __('Acheter', 'wc-rental');
}

// Ajouter (Location) après le nom du produit dans le panier et la commande
add_filter('woocommerce_cart_item_name', 'wc_rental_add_rental_to_name', 10, 3);
add_filter('woocommerce_order_item_name', 'wc_rental_add_rental_to_name', 10, 3);

function wc_rental_add_rental_to_name($name, $cart_item, $cart_item_key)
{
    if (isset($cart_item['rental_start_date'])) {
        $name .= ' ' . __('(Location)', 'wc-rental');
    }
    return $name;
}

// Retirer les prix barrés et la somme économisée pour les locations
add_filter('woocommerce_cart_item_price', 'wc_rental_remove_crossed_out_price', 10, 3);
add_filter('woocommerce_cart_item_subtotal', 'wc_rental_remove_crossed_out_price', 10, 3);

function wc_rental_remove_crossed_out_price($price, $cart_item, $cart_item_key)
{
    if (isset($cart_item['rental_start_date'])) {
        return wc_price($cart_item['rental_price']);
    }
    return $price;
}



function pgr_register_elementor_widgets()
{
    // Vérifier si Elementor est activé
    if (did_action('elementor/loaded')) {
        // Inclure le fichier du widget
        require_once(plugin_dir_path(__FILE__) . 'includes/elementor-widgets/class-woocommerce-search-filter-widget.php');

        // Enregistrer le widget
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new Improved_WooCommerce_Search_Filter_Widget());
    }
}
add_action('init', 'pgr_register_elementor_widgets');

function handle_quotation_submission()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quotation_submit'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quotations';

        $wpdb->insert(
            $table_name,
            array(
                'time' => current_time('mysql'),
                'name' => sanitize_text_field($_POST['name']),
                'email' => sanitize_email($_POST['email']),
                'nom_piece' => sanitize_text_field($_POST['nom_piece']),
                'numero_chassis' => sanitize_text_field($_POST['numero_chassis']),
                'marque' => sanitize_text_field($_POST['marque']),
                'modele' => sanitize_text_field($_POST['modele']),
                'sous_modele' => sanitize_text_field($_POST['sous_modele']),
                'generation' => sanitize_text_field($_POST['generation']),
                'annee' => sanitize_text_field($_POST['annee']),
                'autres_informations' => sanitize_textarea_field($_POST['autres_informations']),
            )
        );

        // Rediriger ou afficher un message de confirmation
    }
}

add_action('init', 'handle_quotation_submission');

function add_quotations_menu()
{
    add_menu_page(
        'Cotations',
        'Cotations',
        'manage_options',
        'quotations',
        'display_quotations_page',
        'dashicons-list-view',
        6
    );
}
add_action('admin_menu', 'add_quotations_menu');

function display_quotations_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quotations';
    $quotations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC");

    echo '<div class="wrap">';
    echo '<h1>Cotations</h1>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>Date</th><th>Nom</th><th>Email</th><th>Pièce</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    foreach ($quotations as $quotation) {
        echo '<tr>';
        echo '<td>' . esc_html($quotation->time) . '</td>';
        echo '<td>' . esc_html($quotation->name) . '</td>';
        echo '<td>' . esc_html($quotation->email) . '</td>';
        echo '<td>' . esc_html($quotation->nom_piece) . '</td>';
        echo '<td><a href="#">Voir détails</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}
