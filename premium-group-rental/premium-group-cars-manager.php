<?php
/*
Plugin Name: Premium Group - Cars Manager
Description: Plugin pour gérer les produits de type Voiture dans WooCommerce.
Version: 1.2
Author: Bermen
*/

if (!defined('ABSPATH')) {
    exit;
}

class PremiumGroupCarsManager
{
    public function __construct()
    {
        add_action('init', [$this, 'register_car_taxonomies']);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_car_attributes_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_car_attributes_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_car_attributes_fields']);
        add_action('woocommerce_single_product_summary', [$this, 'display_car_information'], 25);
        add_filter('woocommerce_get_sections_products', [$this, 'add_car_attributes_section']);
        add_filter('woocommerce_get_settings_products', [$this, 'add_car_attributes_settings'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('woocommerce_product_tabs', [$this, 'remove_product_tabs'], 98);
    }

    public function register_car_taxonomies()
    {
        $taxonomies = [
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

        foreach ($taxonomies as $taxonomy => $label) {
            register_taxonomy($taxonomy, 'product', [
                'label' => $label,
                'rewrite' => ['slug' => $taxonomy],
                'hierarchical' => false,
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'show_in_rest' => true,
            ]);
        }
    }

    public function add_car_attributes_tab($tabs)
    {
        $tabs['car_attributes'] = [
            'label' => __('Attributs du véhicule', 'text-domain'),
            'target' => 'car_attributes_data',
            'class' => ['hide_if_grouped'],
        ];
        return $tabs;
    }

    public function add_car_attributes_fields()
    {
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
                    'data-taxonomy' => $key,
                ],
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
                $term = term_exists($_POST[$field], $field);
                if (!$term) {
                    $term = wp_insert_term($_POST[$field], $field);
                }
                if (!is_wp_error($term)) {
                    wp_set_object_terms($post_id, $term['term_id'], $field);
                }
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
        foreach ($fields as $label => $taxonomy) {
            $terms = get_the_terms($product->get_id(), $taxonomy);
            if ($terms && !is_wp_error($terms)) {
                $value = $terms[0]->name;
                echo '<tr>';
                echo '<th>' . esc_html($label) . '</th>';
                echo '<td>' . esc_html($value) . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        echo '</div>';
    }

    public function add_car_attributes_section($sections)
    {
        $sections['car_attributes'] = __('Attributs du véhicule', 'text-domain');
        return $sections;
    }

    public function add_car_attributes_settings($settings, $current_section)
    {
        if ($current_section == 'car_attributes') {
            $settings = array();
            $settings[] = array(
                'name' => __('Paramètres des attributs du véhicule', 'text-domain'),
                'type' => 'title',
                'desc' => __('Configurez les attributs du véhicule ici.', 'text-domain'),
                'id' => 'car_attributes_settings'
            );

            $taxonomies = $this->get_car_taxonomies();
            foreach ($taxonomies as $taxonomy => $label) {
                $settings[] = array(
                    'name' => $label,
                    'type' => 'checkbox',
                    'desc' => sprintf(__('Activer l\'attribut %s', 'text-domain'), $label),
                    'id' => 'car_attribute_' . $taxonomy
                );
            }

            $settings[] = array('type' => 'sectionend', 'id' => 'car_attributes_settings');
        }
        return $settings;
    }

    private function get_car_taxonomies()
    {
        return [
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
}

// Initialiser le plugin
new PremiumGroupCarsManager();

// Ajouter cette fonction pour gérer l'autocomplétion AJAX
add_action('wp_ajax_get_car_attribute_suggestions', 'get_car_attribute_suggestions');
add_action('wp_ajax_nopriv_get_car_attribute_suggestions', 'get_car_attribute_suggestions');

function get_car_attribute_suggestions()
{
    check_ajax_referer('car_attributes_nonce', 'nonce');

    $taxonomy = sanitize_text_field($_GET['taxonomy']);
    $term = sanitize_text_field($_GET['term']);

    $suggestions = array();
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'search' => $term,
        'number' => 10
    ));

    foreach ($terms as $term) {
        $suggestions[] = $term->name;
    }

    wp_send_json($suggestions);
}

// Ajouter cette fonction pour gérer l'autocomplétion AJAX dans l'admin
add_action('wp_ajax_get_car_attribute_admin_suggestions', 'get_car_attribute_admin_suggestions');

function get_car_attribute_admin_suggestions()
{
    check_ajax_referer('car_attributes_admin_nonce', 'nonce');

    $taxonomy = sanitize_text_field($_GET['taxonomy']);
    $term = sanitize_text_field($_GET['term']);

    $suggestions = array();
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'search' => $term,
        'number' => 10
    ));

    foreach ($terms as $term) {
        $suggestions[] = array(
            'value' => $term->name,
            'label' => $term->name
        );
    }

    wp_send_json($suggestions);
}

add_action('wp_ajax_update_dynamic_filters', 'update_dynamic_filters');
add_action('wp_ajax_nopriv_update_dynamic_filters', 'update_dynamic_filters');

function update_dynamic_filters()
{
    $attributes = [
        'marque',
        'modele',
        'annee',
        'finition',
        'etat',
        'carrosserie',
        'transmission',
        'moteur',
        'groupe_motopropulseur',
        'type_carburant',
        'couleur_exterieure',
        'couleur_interieure'
    ];

    $response = ['attributes' => []];

    // Get current filter values
    $current_filters = [];
    foreach ($attributes as $attribute) {
        if (isset($_GET[$attribute]) && !empty($_GET[$attribute])) {
            $current_filters[$attribute] = sanitize_text_field($_GET[$attribute]);
        }
    }

    // Get filtered product IDs
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [],
        'meta_query' => []
    ];

    foreach ($current_filters as $taxonomy => $value) {
        $args['tax_query'][] = [
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => $value,
        ];
    }

    if (isset($_GET['min_price']) && isset($_GET['max_price'])) {
        $args['meta_query'][] = [
            'key' => '_price',
            'value' => [intval($_GET['min_price']), intval($_GET['max_price'])],
            'type' => 'NUMERIC',
            'compare' => 'BETWEEN'
        ];
    }

    $product_ids = get_posts($args);

    // Get available options for each attribute
    foreach ($attributes as $attribute) {
        if (!isset($current_filters[$attribute])) {
            $terms = get_terms([
                'taxonomy' => $attribute,
                'hide_empty' => true,
                'object_ids' => $product_ids,
            ]);

            $options = [];
            foreach ($terms as $term) {
                $options[$term->slug] = $term->name;
            }

            $response['attributes'][$attribute] = $options;
        }
    }

    // Get price range
    $price_range = [
        'min' => 0,
        'max' => 0,
    ];

    if (!empty($product_ids)) {
        global $wpdb;
        $price_range = $wpdb->get_row("
            SELECT MIN(meta_value + 0) as min, MAX(meta_value + 0) as max
            FROM $wpdb->postmeta
            WHERE meta_key = '_price'
            AND post_id IN (" . implode(',', $product_ids) . ")
        ");
    }

    $response['price_range'] = [
        'min' => floor($price_range->min),
        'max' => ceil($price_range->max),
    ];

    wp_send_json($response);
}
