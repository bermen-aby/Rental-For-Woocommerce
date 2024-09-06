<?php
/*
Plugin Name: Premium Group - Spare Parts Manager
Description: Plugin pour gérer les produits de type Pièce détachée dans WooCommerce.
Version: 1.0
Author: Bermen
*/

if (!defined('ABSPATH')) {
    exit;
}

class PremiumGroupSparePartsManager
{
    public function __construct()
    {
        add_action('init', [$this, 'register_taxonomies']);
        add_filter('product_type_selector', [$this, 'add_spare_part_product_type']);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_spare_part_attributes_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_spare_part_attributes_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_spare_part_attributes_fields']);
        add_action('woocommerce_single_product_summary', [$this, 'display_spare_part_information'], 25);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('woocommerce_product_tabs', [$this, 'remove_product_tabs'], 98);
        add_filter('manage_edit-product_columns', [$this, 'customize_product_columns']);
        add_action('manage_product_posts_custom_column', [$this, 'custom_product_column'], 10, 2);
        add_filter('manage_edit-product_sortable_columns', [$this, 'make_product_type_column_sortable']);
        add_action('pre_get_posts', [$this, 'product_type_orderby']);
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

        // Enregistrer les taxonomies pour les attributs de pièce détachée
        $spare_part_attributes = [
            'nom_piece',
            'numero_chassis',
            'marque',
            'modele',
            'sous_modele',
            'generation',
            'annee',
            'autres_informations'
        ];

        foreach ($spare_part_attributes as $attribute) {
            register_taxonomy(
                'spare_part_' . $attribute,
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

        // Enregistrer la taxonomie pour les types de pièces détachées
        register_taxonomy(
            'spare_part_type',
            'product',
            [
                'label' => __('Type de pièce détachée', 'text-domain'),
                'hierarchical' => true,
                'show_ui' => true,
                'show_in_quick_edit' => true,
                'meta_box_cb' => false,
            ]
        );

        // Ajouter les termes par défaut pour les types de pièces détachées
        $spare_part_types = ['Accessoires', 'Carrosserie', 'Chassis', 'Intérieur', 'Moteur', 'Pneumatique'];
        foreach ($spare_part_types as $type) {
            if (!term_exists($type, 'spare_part_type')) {
                wp_insert_term($type, 'spare_part_type');
            }
        }
    }

    public function add_spare_part_product_type($types)
    {
        $types['spare_part'] = __('Pièce détachée', 'text-domain');
        return $types;
    }

    public function add_spare_part_attributes_tab($tabs)
    {
        $tabs['spare_part_attributes'] = [
            'label' => __('Attributs de la pièce détachée', 'text-domain'),
            'target' => 'spare_part_attributes_data',
            'class' => [],
        ];
        return $tabs;
    }

    public function add_spare_part_attributes_fields()
    {
        global $post;

        echo '<div id="spare_part_attributes_data" class="panel woocommerce_options_panel">';

        $fields = [
            'type_accessoire' => 'Type d\'accessoire',
            'numero_chassis' => 'Numéro de chassis',
            'marque' => 'Marque',
            'modele' => 'Modèle',
            'sous_modele' => 'Sous-modèle',
            'generation' => 'Génération',
            'annee' => 'Année'
        ];

        foreach ($fields as $key => $label) {
            $terms = get_the_terms($post->ID, 'spare_part_' . $key);
            $value = $terms ? $terms[0]->name : '';

            woocommerce_wp_text_input([
                'id' => 'spare_part_' . $key,
                'label' => __($label, 'text-domain'),
                'desc_tip' => 'true',
                'description' => __('Entrez ' . strtolower($label) . ' de la pièce détachée.', 'text-domain'),
                'class' => 'spare-part-attribute-input',
                'custom_attributes' => [
                    'data-attribute' => $key,
                ],
                'value' => $value,
            ]);
        }

        // Ajout du champ pour le type de pièce détachée
        $spare_part_types = get_terms([
            'taxonomy' => 'spare_part_type',
            'hide_empty' => false,
        ]);

        $options = [];
        foreach ($spare_part_types as $type) {
            $options[$type->term_id] = $type->name;
        }

        woocommerce_wp_select([
            'id' => 'spare_part_type',
            'label' => __('Type de pièce détachée', 'text-domain'),
            'options' => $options,
            'value' => $this->get_product_spare_part_type($post->ID),
        ]);

        echo '</div>';
    }

    public function save_spare_part_attributes_fields($post_id)
    {
        $fields = [
            'type_accessoire',
            'numero_chassis',
            'marque',
            'modele',
            'sous_modele',
            'generation',
            'annee'
        ];

        foreach ($fields as $field) {
            if (isset($_POST['spare_part_' . $field])) {
                $term = term_exists($_POST['spare_part_' . $field], 'spare_part_' . $field);
                if (!$term) {
                    $term = wp_insert_term($_POST['spare_part_' . $field], 'spare_part_' . $field);
                }
                if (!is_wp_error($term)) {
                    wp_set_object_terms($post_id, intval($term['term_id']), 'spare_part_' . $field);
                }
            }
        }

        // Sauvegarde du type de pièce détachée
        if (isset($_POST['spare_part_type'])) {
            wp_set_object_terms($post_id, intval($_POST['spare_part_type']), 'spare_part_type');
        }
    }

    public function display_spare_part_information()
    {
        global $product;

        $fields = [
            'Type d\'accessoire' => 'type_accessoire',
            'Numéro de chassis' => 'numero_chassis',
            'Marque' => 'marque',
            'Modèle' => 'modele',
            'Sous-modèle' => 'sous_modele',
            'Génération' => 'generation',
            'Année' => 'annee'
        ];

        echo '<div class="spare-part-information">';
        echo '<h2>' . __('Caractéristiques de la pièce détachée', 'text-domain') . '</h2>';
        echo '<table class="spare-part-attributes-table">';
        foreach ($fields as $label => $key) {
            $terms = get_the_terms($product->get_id(), 'spare_part_' . $key);
            if ($terms && !is_wp_error($terms)) {
                $value = $terms[0]->name;
                echo '<tr>';
                echo '<th>' . esc_html($label) . '</th>';
                echo '<td>' . esc_html($value) . '</td>';
                echo '</tr>';
            }
        }

        // Affichage du type de pièce détachée
        $spare_part_type = $this->get_product_spare_part_type($product->get_id());
        if ($spare_part_type) {
            echo '<tr>';
            echo '<th>' . esc_html__('Type de pièce détachée', 'text-domain') . '</th>';
            echo '<td>' . esc_html($spare_part_type) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('spare-part-attributes-autocomplete', plugin_dir_url(__FILE__) . 'js/spare-part-attributes-autocomplete.js', array('jquery', 'jquery-ui-autocomplete'), '1.0', true);
        wp_localize_script('spare-part-attributes-autocomplete', 'spare_part_attributes', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spare_part_attributes_nonce')
        ));
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('post.php' != $hook && 'post-new.php' != $hook) {
            return;
        }
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('spare-part-attributes-admin', plugin_dir_url(__FILE__) . 'js/spare-part-attributes-admin.js', array('jquery', 'jquery-ui-autocomplete'), '1.0', true);
        wp_localize_script('spare-part-attributes-admin', 'spare_part_attributes_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spare_part_attributes_admin_nonce')
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



    public function save_product_type($post_id, $post, $update)
    {
        if (!isset($_POST['_product_type_custom'])) {
            return;
        }

        $product_type = sanitize_text_field($_POST['_product_type_custom']);
        wp_set_object_terms($post_id, $product_type, 'product_type_custom');
    }

    private function get_product_spare_part_type($product_id)
    {
        $terms = get_the_terms($product_id, 'spare_part_type');
        if ($terms && !is_wp_error($terms)) {
            return $terms[0]->name;
        }
        return '';
    }
}

// Initialiser le plugin
new PremiumGroupSparePartsManager();

// Fonction pour gérer l'autocomplétion AJAX
add_action('wp_ajax_get_spare_part_attribute_suggestions', 'get_spare_part_attribute_suggestions');
add_action('wp_ajax_nopriv_get_spare_part_attribute_suggestions', 'get_spare_part_attribute_suggestions');


function get_spare_part_attribute_suggestions()
{
    check_ajax_referer('spare_part_attributes_nonce', 'nonce');

    $attribute = sanitize_text_field($_GET['attribute']);
    $term = sanitize_text_field($_GET['term']);

    $terms = get_terms([
        'taxonomy' => 'spare_part_' . $attribute,
        'hide_empty' => false,
        'name__like' => $term,
    ]);

    $suggestions = array_map(function ($term) {
        return $term->name;
    }, $terms);

    wp_send_json($suggestions);
}

// Fonction pour gérer l'autocomplétion AJAX dans l'admin
add_action('wp_ajax_get_spare_part_attribute_admin_suggestions', 'get_spare_part_attribute_admin_suggestions');

function get_spare_part_attribute_admin_suggestions()
{
    check_ajax_referer('spare_part_attributes_admin_nonce', 'nonce');

    $attribute = sanitize_text_field($_GET['attribute']);
    $term = sanitize_text_field($_GET['term']);

    $terms = get_terms([
        'taxonomy' => 'spare_part_' . $attribute,
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

// Ajout d'un filtre pour modifier le comportement du bouton "Ajouter au panier" pour les pièces détachées
add_filter('woocommerce_product_single_add_to_cart_text', 'custom_add_to_cart_text', 10, 2);
add_filter('woocommerce_product_add_to_cart_text', 'custom_add_to_cart_text', 10, 2);

function custom_add_to_cart_text($text, $product)
{
    $terms = get_the_terms($product->get_id(), 'product_type_custom');
    if ($terms && !is_wp_error($terms)) {
        $product_type = $terms[0]->slug;
        if ($product_type === 'piece_detachee') {
            return __('Ajouter au panier', 'text-domain');
        }
    }
    return $text;
}

// Ajout d'une colonne pour afficher le type de pièce détachée dans la liste des produits de l'admin
add_filter('manage_edit-product_columns', 'add_spare_part_type_column', 20);
add_action('manage_product_posts_custom_column', 'fill_spare_part_type_column', 10, 2);

function add_spare_part_type_column($columns)
{
    $columns['spare_part_type'] = __('Type de pièce détachée', 'text-domain');
    return $columns;
}

function fill_spare_part_type_column($column, $post_id)
{
    if ($column === 'spare_part_type') {
        $terms = get_the_terms($post_id, 'spare_part_type');
        if ($terms && !is_wp_error($terms)) {
            echo esc_html($terms[0]->name);
        } else {
            echo '—';
        }
    }
}

// Ajout d'un filtre pour les types de pièces détachées dans la liste des produits de l'admin
add_action('restrict_manage_posts', 'add_spare_part_type_filter');
add_filter('parse_query', 'filter_products_by_spare_part_type');

function add_spare_part_type_filter()
{
    global $typenow;
    if ($typenow === 'product') {
        $selected = isset($_GET['spare_part_type']) ? $_GET['spare_part_type'] : '';
        $info_taxonomy = get_taxonomy('spare_part_type');
        wp_dropdown_categories(array(
            'show_option_all' => __("Tous les types de pièces détachées"),
            'taxonomy' => 'spare_part_type',
            'name' => 'spare_part_type',
            'orderby' => 'name',
            'selected' => $selected,
            'show_count' => true,
            'hide_empty' => false,
        ));
    }
}

function filter_products_by_spare_part_type($query)
{
    global $pagenow;
    $q_vars = &$query->query_vars;
    if ($pagenow === 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] === 'product' && isset($_GET['spare_part_type']) && is_numeric($_GET['spare_part_type'])) {
        $q_vars['tax_query'] = array(
            array(
                'taxonomy' => 'spare_part_type',
                'field' => 'id',
                'terms' => $_GET['spare_part_type'],
            )
        );
    }
}

// Ajout de métadonnées structurées pour les pièces détachées
add_action('woocommerce_single_product_summary', 'add_spare_part_structured_data', 60);

function add_spare_part_structured_data()
{
    global $product;

    $terms = get_the_terms($product->get_id(), 'product_type_custom');
    if (!$terms || is_wp_error($terms) || $terms[0]->slug !== 'piece_detachee') {
        return;
    }

    $spare_part_data = array(
        '@context' => 'https://schema.org/',
        '@type' => 'Product',
        'name' => $product->get_name(),
        'sku' => $product->get_sku(),
        'description' => $product->get_short_description(),
        'brand' => array(
            '@type' => 'Brand',
            'name' => get_post_meta($product->get_id(), 'spare_part_marque', true),
        ),
        'model' => get_post_meta($product->get_id(), 'spare_part_modele', true),
        'productionDate' => get_post_meta($product->get_id(), 'spare_part_annee', true),
    );

    echo '<script type="application/ld+json">' . json_encode($spare_part_data) . '</script>';
}
