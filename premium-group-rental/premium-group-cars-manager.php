<?php
/*
Plugin Name: Premium Group - Cars Manager
Description: Plugin pour gérer les produits de type Voiture et Pièce détachée dans WooCommerce.
Version: 1.6
Author: Bermen
*/

if (!defined('ABSPATH')) {
    exit;
}

class PremiumGroupCarsManager
{
    public function __construct()
    {
        add_action('init', [$this, 'register_taxonomies']);
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
        add_action('save_post_product', [$this, 'save_product_type'], 10, 3);
    }

    public function register_taxonomies()
    {
        // Enregistrer la taxonomie pour le type de produit
        register_taxonomy(
            'product_type_custom',
            'product',
            [
                'label' => __('Type de produit', 'text-domain'),
                'hierarchical' => false,
                'show_ui' => false,
                'show_in_quick_edit' => false,
                'meta_box_cb' => false,
            ]
        );

        // Enregistrer les taxonomies pour les attributs de voiture
        $car_attributes = [
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

        foreach ($car_attributes as $attribute) {
            register_taxonomy(
                'car_' . $attribute,
                'product',
                [
                    'label' => __(ucfirst(str_replace('_', ' ', $attribute)), 'text-domain'),
                    'hierarchical' => false,
                    'show_ui' => false,
                    'show_in_quick_edit' => false,
                    'meta_box_cb' => false,
                ]
            );
        }

        // Enregistrer la taxonomie pour les options de location/vente
        register_taxonomy(
            'product_sale_type',
            'product',
            [
                'label' => __('Type de vente', 'text-domain'),
                'hierarchical' => false,
                'show_ui' => false,
                'show_in_quick_edit' => false,
                'meta_box_cb' => false,
            ]
        );
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
            'class' => [],
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
            $terms = get_the_terms($post->ID, 'car_' . $key);
            $value = $terms ? $terms[0]->name : '';

            woocommerce_wp_text_input([
                'id' => 'car_' . $key,
                'label' => __($label, 'text-domain'),
                'desc_tip' => 'true',
                'description' => __('Entrez la ' . strtolower($label) . ' de la voiture.', 'text-domain'),
                'class' => 'car-attribute-input',
                'custom_attributes' => [
                    'data-attribute' => $key,
                ],
                'value' => $value,
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
            if (isset($_POST['car_' . $field])) {
                $term = term_exists($_POST['car_' . $field], 'car_' . $field);
                if (!$term) {
                    $term = wp_insert_term($_POST['car_' . $field], 'car_' . $field);
                }
                if (!is_wp_error($term)) {
                    wp_set_object_terms($post_id, intval($term['term_id']), 'car_' . $field);
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
        foreach ($fields as $label => $key) {
            $terms = get_the_terms($product->get_id(), 'car_' . $key);
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
            $terms = get_the_terms($post_id, 'product_type_custom');
            if ($terms && !is_wp_error($terms)) {
                echo esc_html(ucfirst($terms[0]->name));
            } else {
                echo esc_html__('Non défini', 'text-domain');
            }
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
            $query->set('tax_query', array(
                array(
                    'taxonomy' => 'product_type_custom',
                    'field' => 'name',
                )
            ));
            $query->set('orderby', 'name');
        }
    }

    public function add_product_type_field()
    {
        global $post;

        $terms = get_the_terms($post->ID, 'product_type_custom');
        $current_type = $terms ? $terms[0]->slug : 'piece_detachee';

        woocommerce_wp_select([
            'id' => '_product_type_custom',
            'label' => __('Type de produit', 'text-domain'),
            'options' => [
                'piece_detachee' => __('Pièce détachée', 'text-domain'),
                'car' => __('Voiture', 'text-domain'),
            ],
            'value' => $current_type,
        ]);
    }

    public function save_product_type($post_id, $post, $update)
    {
        if (!isset($_POST['_product_type_custom'])) {
            return;
        }

        $product_type = sanitize_text_field($_POST['_product_type_custom']);
        wp_set_object_terms($post_id, $product_type, 'product_type_custom');
    }
}

// Initialiser le plugin
new PremiumGroupCarsManager();

function limit_etat_terms($terms, $taxonomies, $args)
{
    if (in_array('car_etat', $taxonomies)) {
        $allowed_terms = array('neuf', 'comme-neuf', 'occasion');
        $terms = array_filter($terms, function ($term) use ($allowed_terms) {
            return in_array($term->slug, $allowed_terms);
        });
    }
    return $terms;
}
add_filter('get_terms', 'limit_etat_terms', 10, 3);

// Fonction pour gérer l'autocomplétion AJAX
add_action('wp_ajax_get_car_attribute_suggestions', 'get_car_attribute_suggestions');
add_action('wp_ajax_nopriv_get_car_attribute_suggestions', 'get_car_attribute_suggestions');

function get_car_attribute_suggestions()
{
    check_ajax_referer('car_attributes_nonce', 'nonce');

    $attribute = sanitize_text_field($_GET['attribute']);
    $term = sanitize_text_field($_GET['term']);

    $terms = get_terms([
        'taxonomy' => 'car_' . $attribute,
        'hide_empty' => false,
        'name__like' => $term,
    ]);

    $suggestions = array_map(function ($term) {
        return $term->name;
    }, $terms);

    wp_send_json($suggestions);
}

// Fonction pour gérer l'autocomplétion AJAX dans l'admin
add_action('wp_ajax_get_car_attribute_admin_suggestions', 'get_car_attribute_admin_suggestions');

function get_car_attribute_admin_suggestions()
{
    check_ajax_referer('car_attributes_admin_nonce', 'nonce');

    $attribute = sanitize_text_field($_GET['attribute']);
    $term = sanitize_text_field($_GET['term']);

    $terms = get_terms([
        'taxonomy' => 'car_' . $attribute,
        'hide_empty' => false,
        'name__like' => $term,
    ]);

    $result = array_map(function ($term) {
        return [
            'value' => $term->name,
            'label' => $term->name
        ];
    }, $terms);

    wp_send_json($result);
}
