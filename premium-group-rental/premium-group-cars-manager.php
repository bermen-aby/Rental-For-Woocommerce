<?php
/*
Plugin Name: Premium Group - Cars Manager
Description: Plugin pour gérer les produits de type Voiture et Pièce détachée dans WooCommerce.
Version: 1.5
Author: Bermen
*/

if (!defined('ABSPATH')) {
    exit;
}

class PremiumGroupCarsManager
{
    public function __construct()
    {
        add_filter('product_type_selector', [$this, 'add_car_product_type']);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_car_attributes_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_car_attributes_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_car_attributes_fields']);
        add_action('woocommerce_single_product_summary', [$this, 'display_car_information'], 25);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('woocommerce_product_tabs', [$this, 'remove_product_tabs'], 98);
        add_filter('manage_edit-product_columns', [$this, 'customize_product_columns']);
        add_action('manage_product_posts_custom_column', [$this, 'custom_product_column'], 10, 2);
        add_filter('manage_edit-product_sortable_columns', [$this, 'make_product_type_column_sortable']);
        add_action('pre_get_posts', [$this, 'product_type_orderby']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_product_type_field']);
    }

    public function add_car_product_type($types)
    {
        $types['car'] = __('Voiture', 'text-domain');
        return $types;
    }

    public function add_car_attributes_tab($tabs)
    {
        $tabs['car_attributes'] = [
            'label' => __('Attributs du véhicule', 'text-domain'),
            'target' => 'car_attributes_data',
            'class' => [], // Retiré la classe 'show_if_car' pour que l'onglet soit toujours visible
        ];
        return $tabs;
    }

    public function add_car_attributes_fields()
    {
        global $post;

        echo '<div id="car_attributes_data" class="panel woocommerce_options_panel">';

        $fields = [
            'marque' => 'Marque',
            'modele' => 'Modèle',
            'annee' => 'Année',
            'numero_inventaire' => 'Numéro d\'inventaire',
            'numero_vin' => 'Numéro VIN',
            'finition' => 'Finition',
            'etat' => 'État',
            'carrosserie' => 'Carrosserie',
            'transmission' => 'Transmission',
            'moteur' => 'Moteur',
            'groupe_motopropulseur' => 'Groupe motopropulseur',
            'type_carburant' => 'Type de carburant',
            'economie_carburant' => 'Économie de carburant',
            'kilometrage' => 'Kilométrage',
            'couleur_exterieure' => 'Couleur extérieure',
            'couleur_interieure' => 'Couleur intérieure',
            'consommation_ville' => 'Consommation de carburant: Ville',
            'consommation_autoroute' => 'Consommation de carburant: Autoroute',
        ];

        foreach ($fields as $key => $label) {
            woocommerce_wp_text_input([
                'id' => $key,
                'label' => __($label, 'text-domain'),
                'desc_tip' => 'true',
                'description' => __('Entrez la ' . strtolower($label) . ' de la voiture.', 'text-domain'),
                'class' => 'car-attribute-input',
                'custom_attributes' => [
                    'data-attribute' => $key,
                ],
                'value' => get_post_meta($post->ID, $key, true),
            ]);
        }

        echo '</div>';
    }

    public function save_car_attributes_fields($post_id)
    {
        $fields = [
            'marque',
            'modele',
            'annee',
            'numero_inventaire',
            'numero_vin',
            'finition',
            'etat',
            'carrosserie',
            'transmission',
            'moteur',
            'groupe_motopropulseur',
            'type_carburant',
            'economie_carburant',
            'kilometrage',
            'couleur_exterieure',
            'couleur_interieure',
            'consommation_ville',
            'consommation_autoroute'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    public function display_car_information()
    {
        global $product;

        $fields = [
            'Marque' => 'marque',
            'Modèle' => 'modele',
            'Année' => 'annee',
            'Numéro d\'inventaire' => 'numero_inventaire',
            'Numéro VIN' => 'numero_vin',
            'Finition' => 'finition',
            'État' => 'etat',
            'Carrosserie' => 'carrosserie',
            'Transmission' => 'transmission',
            'Moteur' => 'moteur',
            'Groupe motopropulseur' => 'groupe_motopropulseur',
            'Type de carburant' => 'type_carburant',
            'Économie de carburant' => 'economie_carburant',
            'Kilométrage' => 'kilometrage',
            'Couleur extérieure' => 'couleur_exterieure',
            'Couleur intérieure' => 'couleur_interieure',
            'Consommation de carburant: Ville' => 'consommation_ville',
            'Consommation de carburant: Autoroute' => 'consommation_autoroute',
        ];

        echo '<div class="car-information">';
        echo '<h2>' . __('Caractéristiques du véhicule', 'text-domain') . '</h2>';
        echo '<table class="car-attributes-table">';
        foreach ($fields as $label => $key) {
            $value = get_post_meta($product->get_id(), $key, true);
            if ($value) {
                echo '<tr>';
                echo '<th>' . esc_html($label) . '</th>';
                echo '<td>' . esc_html($value) . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        echo '</div>';
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('car-attributes-autocomplete', plugin_dir_url(__FILE__) . 'js/car-attributes-autocomplete.js', array('jquery', 'jquery-ui-autocomplete'), '1.0', true);
        wp_localize_script('car-attributes-autocomplete', 'car_attributes', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('car_attributes_nonce')
        ));
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('post.php' != $hook && 'post-new.php' != $hook) {
            return;
        }
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('car-attributes-admin', plugin_dir_url(__FILE__) . 'js/car-attributes-admin.js', array('jquery', 'jquery-ui-autocomplete'), '1.0', true);
        wp_localize_script('car-attributes-admin', 'car_attributes_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('car_attributes_admin_nonce')
        ));
    }

    public function remove_product_tabs($tabs)
    {
        unset($tabs['additional_information']);
        return $tabs;
    }

    public function customize_product_columns($columns)
    {
        $columns['product_type'] = __('Type de produit', 'text-domain');
        return $columns;
    }

    public function custom_product_column($column, $post_id)
    {
        if ($column === 'product_type') {
            $product_type = get_post_meta($post_id, '_product_type', true);
            echo esc_html(ucfirst($product_type ?: 'Pièce détachée'));
        }
    }

    public function make_product_type_column_sortable($columns)
    {
        $columns['product_type'] = 'product_type';
        return $columns;
    }

    public function product_type_orderby($query)
    {
        if (!is_admin()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ('product_type' === $orderby) {
            $query->set('meta_key', '_product_type');
            $query->set('orderby', 'meta_value');
        }
    }

    public function add_product_type_field()
    {
        global $post;

        // Ajout du sélecteur de type de produit
        woocommerce_wp_select([
            'id' => '_product_type',
            'label' => __('Type de produit', 'text-domain'),
            'options' => [
                'piece_detachee' => __('Pièce détachée', 'text-domain'),
                'car' => __('Voiture', 'text-domain'),
            ],
            'value' => get_post_meta($post->ID, '_product_type', true) ?: 'piece_detachee',
        ]);
    }
}

// Initialiser le plugin
new PremiumGroupCarsManager();

// Ajouter cette fonction pour gérer l'autocomplétion AJAX
add_action('wp_ajax_get_car_attribute_suggestions', 'get_car_attribute_suggestions');
add_action('wp_ajax_nopriv_get_car_attribute_suggestions', 'get_car_attribute_suggestions');

function get_car_attribute_suggestions()
{
    check_ajax_referer('car_attributes_nonce', 'nonce');

    global $wpdb;

    $attribute = sanitize_text_field($_GET['attribute']);
    $term = sanitize_text_field($_GET['term']);

    $suggestions = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
        $attribute,
        '%' . $wpdb->esc_like($term) . '%'
    ));

    wp_send_json($suggestions);
}

// Ajouter cette fonction pour gérer l'autocomplétion AJAX dans l'admin
add_action('wp_ajax_get_car_attribute_admin_suggestions', 'get_car_attribute_admin_suggestions');

function get_car_attribute_admin_suggestions()
{
    check_ajax_referer('car_attributes_admin_nonce', 'nonce');

    global $wpdb;

    $attribute = sanitize_text_field($_GET['attribute']);
    $term = sanitize_text_field($_GET['term']);

    $suggestions = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
        $attribute,
        '%' . $wpdb->esc_like($term) . '%'
    ));

    $result = array();
    foreach ($suggestions as $suggestion) {
        $result[] = array(
            'value' => $suggestion,
            'label' => $suggestion
        );
    }

    wp_send_json($result);
}

// Ajout d'un filtre pour masquer les champs d'attributs de véhicule pour les produits de type "Pièce détachée"
add_action('admin_footer', 'hide_car_attributes_for_spare_parts');

function hide_car_attributes_for_spare_parts()
{
?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleCarAttributes() {
                var productType = $('#_product_type').val();
                if (productType === 'piece_detachee') {
                    $('#car_attributes_data').hide();
                } else {
                    $('#car_attributes_data').show();
                }
            }

            $('#_product_type').change(toggleCarAttributes);
            toggleCarAttributes(); // Run on page load
        });
    </script>
<?php
}
