<?php
class Improved_WooCommerce_Search_Filter_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'improved_woocommerce_search_filter';
    }

    public function get_title()
    {
        return __('Filtres Vente/Location - WooCommerce', 'text-domain');
    }

    public function get_icon()
    {
        return 'eicon-search';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function _register_controls()
    {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Contenu', 'text-domain'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'enable_quotation',
            [
                'label' => __('Activer la cotation', 'text-domain'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'text-domain'),
                'label_off' => __('Non', 'text-domain'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'product_type',
            [
                'label' => __('Type de produit', 'text-domain'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all' => __('Tous', 'text-domain'),
                    'car' => __('Voiture', 'text-domain'),
                    'piece_detachee' => __('Pièce détachée', 'text-domain'),
                ],
            ]
        );

        $this->add_control(
            'sale_rental_type',
            [
                'label' => __('Vente/Location', 'text-domain'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all' => __('Tous', 'text-domain'),
                    'sale' => __('Vente', 'text-domain'),
                    'rental' => __('Location', 'text-domain'),
                ],
            ]
        );

        $this->add_control(
            'product_categories',
            [
                'label' => __('Catégories de produits', 'text-domain'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'options' => $this->get_product_categories(),
                'multiple' => true,
            ]
        );

        $this->add_control(
            'display_attributes',
            [
                'label' => __('Attributs à afficher', 'text-domain'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'options' => $this->get_car_attributes(),
                'multiple' => true,
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $attributes = $settings['display_attributes'] ? $settings['display_attributes'] : array_keys($this->get_car_attributes());
        $max_price = $this->get_max_product_price();
        $min_price = $this->get_min_product_price();
        $currency_symbol = get_woocommerce_currency_symbol();

?>
        <div class="woocommerce-search-filter-widget" id="woocommerce-search-filter-widget">
            <?php if ($settings['enable_quotation'] === 'yes'): ?>
                <?php $this->render_quotation_form($attributes); ?>
            <?php else: ?>
                <?php $this->render_search_form($attributes, $max_price, $min_price, $currency_symbol); ?>
            <?php endif; ?>
        </div>
        <script>
            jQuery(document).ready(function($) {

                var widget = $('#woocommerce-search-filter-widget');
                var isQuotation = <?php echo $settings['enable_quotation'] === 'yes' ? 'true' : 'false'; ?>;

                function toggleFormFields() {
                    if (isQuotation) {
                        widget.find('.price-filter, .search-button').hide();
                        widget.find('.quotation-fields').show();
                        widget.find('select').each(function() {
                            var select = $(this);
                            var input = $('<input type="text" name="' + select.attr('name') + '" id="' + select.attr('id') + '" class="quotation-input" placeholder="' + select.data('placeholder') + '">');
                            select.replaceWith(input);
                        });
                        widget.find('button[type="submit"]').text('<?php _e('Envoyer', 'text-domain'); ?>');
                    } else {
                        widget.find('.price-filter, .search-button').show();
                        widget.find('.quotation-fields').hide();
                        widget.find('.quotation-input').each(function() {
                            var input = $(this);
                            var select = $('<select name="' + input.attr('name') + '" id="' + input.attr('id') + '" class="dynamic-filter select2-filter" data-placeholder="' + input.attr('placeholder') + '"></select>');
                            input.replaceWith(select);
                        });
                        widget.find('button[type="submit"]').text('<?php _e('Rechercher', 'text-domain'); ?>');
                        // Réinitialiser les select2 et autres plugins JS si nécessaire
                    }
                }

                toggleFormFields();

                $('.select2-filter').select2({
                    width: '100%',
                    placeholder: function() {
                        return $(this).data('placeholder');
                    },
                    allowClear: true
                });

                var priceSlider = document.getElementById('price-range-slider');
                var minPrice = parseInt($('#min_price').val());
                var maxPrice = parseInt($('#max_price').val());

                noUiSlider.create(priceSlider, {
                    start: [minPrice, maxPrice],
                    connect: true,
                    range: {
                        'min': minPrice,
                        'max': maxPrice
                    },
                    format: {
                        to: function(value) {
                            return Math.round(value);
                        },
                        from: function(value) {
                            return value;
                        }
                    }
                });

                var minPriceInput = document.getElementById('min_price');
                var maxPriceInput = document.getElementById('max_price');

                priceSlider.noUiSlider.on('update', function(values, handle) {
                    var value = values[handle];
                    if (handle) {
                        maxPriceInput.value = value;
                    } else {
                        minPriceInput.value = value;
                    }
                });

                minPriceInput.addEventListener('change', function() {
                    priceSlider.noUiSlider.set([this.value, null]);
                });

                maxPriceInput.addEventListener('change', function() {
                    priceSlider.noUiSlider.set([null, this.value]);
                });

                var xhr;

                function updateFilters() {
                    if (xhr && xhr.readyState != 4) {
                        xhr.abort();
                    }

                    var formData = $('#search-filter-form').serialize();
                    xhr = $.ajax({
                        url: car_attributes.ajax_url,
                        data: formData + '&action=update_dynamic_filters_elementor',
                        success: function(response) {
                            $.each(response, function(attribute, options) {
                                var select = $('select[name="' + attribute + '"]');
                                var currentValue = select.val();
                                var placeholder = select.data('placeholder');
                                select.empty();
                                select.append($('<option>', {
                                    value: '',
                                    text: placeholder
                                }));
                                $.each(options, function(value, label) {
                                    select.append($('<option>', {
                                        value: value,
                                        text: label
                                    }));
                                });
                                select.val(currentValue).trigger('change.select2');
                            });

                            if (response.price_range) {
                                priceSlider.noUiSlider.updateOptions({
                                    range: {
                                        'min': response.price_range.min,
                                        'max': response.price_range.max
                                    }
                                });
                            }
                        }
                    });
                }

                $('.dynamic-filter').on('change', function() {
                    updateFilters();
                });

                priceSlider.noUiSlider.on('change', function() {
                    updateFilters();
                });

                // Initial update
                updateFilters();
            });
        </script>
        <style>
            .woocommerce-search-filter-widget {
                background-color: #f8f8f8;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                font-size: 14px;
            }

            .filter-container {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .filter-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 10px;
            }

            .price-filter {
                padding: 0 10px;
                text-align: center;
            }

            .select-wrapper {
                position: relative;
                display: flex;
                flex-direction: column;
            }

            .select-wrapper label {
                margin-bottom: 5px;
                font-weight: bold;
            }

            .price-inputs {
                display: flex;
                justify-content: center;
                margin-top: 10px;
            }

            .price-input-wrapper {
                position: relative;
                margin: 0 10px;
            }

            .price-inputs input[type="number"] {
                width: 100px;
                padding-right: 20px;
            }

            .currency-symbol {
                position: absolute;
                right: 5px;
                top: 50%;
                transform: translateY(-50%);
            }

            .search-button-container {
                text-align: center;
            }

            .search-button {
                padding: 10px 20px;
                background-color: #0073aa;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }

            .search-button:hover {
                background-color: #005177;
            }

            .noUi-connect {
                background: #0073aa;
            }

            .noUi-handle {
                border: 1px solid #0073aa;
                background: #fff;
                cursor: pointer;
            }

            .noUi-handle:before,
            .noUi-handle:after {
                background: #0073aa;
            }

            #price-range-slider {
                width: 80%;
                margin: 0 auto;
            }

            .select2-container--default .select2-selection--single {
                border: 1px solid #ddd;
                border-radius: 4px;
                height: 38px;
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 38px;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 36px;
            }

            .select2-container--default .select2-selection--single .select2-selection__placeholder {
                color: #000;
            }

            .select2-container--default .select2-search--dropdown .select2-search__field {
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .select2-dropdown {
                border: 1px solid #ddd;
                border-radius: 4px;
            }
        </style>
    <?php
    }

    protected function render_quotation_form($attributes)
    {
    ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="quotation-form">
            <input type="hidden" name="action" value="submit_quotation">
            <?php wp_nonce_field('submit_quotation', 'quotation_nonce'); ?>

            <div class="quotation-fields">
                <div class="form-row">
                    <label for="quotation_name"><?php _e('Nom', 'text-domain'); ?></label>
                    <input type="text" name="name" id="quotation_name" required>
                </div>
                <div class="form-row">
                    <label for="quotation_email"><?php _e('Email', 'text-domain'); ?></label>
                    <input type="email" name="email" id="quotation_email" required>
                </div>
            </div>

            <div class="filter-grid">
                <?php foreach ($attributes as $attribute): ?>
                    <?php $placeholder = $this->get_translated_attribute_label($attribute); ?>
                    <div class="form-row">
                        <label for="<?php echo esc_attr($attribute); ?>"><?php echo esc_html($placeholder); ?></label>
                        <input type="text" name="<?php echo esc_attr($attribute); ?>" id="<?php echo esc_attr($attribute); ?>" class="quotation-input" placeholder="<?php echo esc_attr($placeholder); ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-row">
                <label for="autres_informations"><?php _e('Autres informations', 'text-domain'); ?></label>
                <textarea name="autres_informations" id="autres_informations"></textarea>
            </div>

            <div class="search-button-container">
                <button type="submit" class="search-button"><?php _e('Envoyer', 'text-domain'); ?></button>
            </div>
        </form>
    <?php
    }

    protected function render_search_form($attributes, $max_price, $min_price, $currency_symbol)
    {
    ?>
        <form action="<?php echo esc_url(home_url('/')); ?>" method="get" id="search-filter-form">
            <input type="hidden" name="post_type" value="product">

            <?php if ($this->get_settings('product_type') !== 'all'): ?>
                <input type="hidden" name="product_type" value="<?php echo esc_attr($this->get_settings('product_type')); ?>">
            <?php endif; ?>

            <?php if ($this->get_settings('sale_rental_type') !== 'all'): ?>
                <input type="hidden" name="sale_rental_type" value="<?php echo esc_attr($this->get_settings('sale_rental_type')); ?>">
            <?php endif; ?>

            <?php if (!empty($this->get_settings('product_categories'))): ?>
                <input type="hidden" name="product_cat" value="<?php echo esc_attr(implode(',', $this->get_settings('product_categories'))); ?>">
            <?php endif; ?>

            <div class="filter-container">
                <div class="filter-grid">
                    <?php foreach ($attributes as $attribute): ?>
                        <?php $placeholder = $this->get_translated_attribute_label($attribute); ?>
                        <div class="select-wrapper">
                            <select name="<?php echo esc_attr($attribute); ?>" id="<?php echo esc_attr($attribute); ?>" class="dynamic-filter select2-filter" data-placeholder="<?php echo esc_attr($placeholder); ?>">
                                <option value=""><?php echo esc_html($placeholder); ?></option>
                                <?php
                                $terms = get_terms([
                                    'taxonomy' => 'pa_' . $attribute,
                                    'hide_empty' => false,
                                ]);
                                foreach ($terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="price-filter">
                    <label for="price-range-slider"><?php _e('Prix', 'text-domain'); ?></label>
                    <div id="price-range-slider"></div>
                    <div class="price-inputs">
                        <div class="price-input-wrapper">
                            <input type="number" id="min_price" name="min_price" value="<?php echo esc_attr($min_price); ?>" min="<?php echo esc_attr($min_price); ?>" max="<?php echo esc_attr($max_price); ?>">
                            <span class="currency-symbol"><?php echo esc_html($currency_symbol); ?></span>
                        </div>
                        <div class="price-input-wrapper">
                            <input type="number" id="max_price" name="max_price" value="<?php echo esc_attr($max_price); ?>" min="<?php echo esc_attr($min_price); ?>" max="<?php echo esc_attr($max_price); ?>">
                            <span class="currency-symbol"><?php echo esc_html($currency_symbol); ?></span>
                        </div>
                    </div>
                </div>
                <div class="search-button-container">
                    <button type="submit" class="search-button"><?php _e('Rechercher', 'text-domain'); ?></button>
                </div>
            </div>
        </form>
<?php
    }

    private function get_translated_attribute_label($attribute)
    {
        $labels = [
            'marque' => __('Marque', 'text-domain'),
            'modele' => __('Modèle', 'text-domain'),
            'annee' => __('Année', 'text-domain'),
            'etat' => __('État', 'text-domain'),
            'finition' => __('Finition', 'text-domain'),
            'carrosserie' => __('Carrosserie', 'text-domain'),
            'transmission' => __('Transmission', 'text-domain'),
            'moteur' => __('Moteur', 'text-domain'),
            'groupe_motopropulseur' => __('Groupe motopropulseur', 'text-domain'),
            'type_carburant' => __('Type de carburant', 'text-domain'),
            'couleur_exterieure' => __('Couleur extérieure', 'text-domain'),
            'couleur_interieure' => __('Couleur intérieure', 'text-domain'),
        ];

        return isset($labels[$attribute]) ? $labels[$attribute] : ucfirst(str_replace('_', ' ', $attribute));
    }

    private function get_attribute_combinations($attributes)
    {
        global $wpdb;
        $combinations = [];

        foreach ($attributes as $attribute) {
            $taxonomy = 'car_' . $attribute;
            $query = $wpdb->prepare(
                "SELECT t.slug, t.name
                FROM {$wpdb->terms} AS t
                INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
                INNER JOIN {$wpdb->term_relationships} AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->posts} AS p ON p.ID = tr.object_id
                WHERE tt.taxonomy = %s AND p.post_type = 'product' AND p.post_status = 'publish'
                GROUP BY t.term_id
                ORDER BY t.name ASC",
                $taxonomy
            );
            $combinations[$attribute] = $wpdb->get_results($query, ARRAY_A);
        }

        return $combinations;
    }

    private function get_product_categories()
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        $options = [];
        foreach ($categories as $category) {
            $options[$category->term_id] = $category->name;
        }

        return $options;
    }

    private function get_car_attributes()
    {
        return [
            'marque' => __('Marque', 'text-domain'),
            'modele' => __('Modèle', 'text-domain'),
            'annee' => __('Année', 'text-domain'),
            'finition' => __('Finition', 'text-domain'),
            'etat' => __('État', 'text-domain'),
            'carrosserie' => __('Carrosserie', 'text-domain'),
            'transmission' => __('Transmission', 'text-domain'),
            'moteur' => __('Moteur', 'text-domain'),
            'groupe_motopropulseur' => __('Groupe motopropulseur', 'text-domain'),
            'type_carburant' => __('Type de carburant', 'text-domain'),
            'couleur_exterieure' => __('Couleur extérieure', 'text-domain'),
            'couleur_interieure' => __('Couleur intérieure', 'text-domain')
        ];
    }

    private function get_max_product_price()
    {
        global $wpdb;
        $max_price = $wpdb->get_var("
            SELECT MAX(meta_value + 0)
            FROM $wpdb->postmeta
            WHERE meta_key = '_price'
        ");
        return $max_price ? ceil($max_price) : 1000000;
    }

    private function get_min_product_price()
    {
        global $wpdb;
        $min_price = $wpdb->get_var("
            SELECT MIN(meta_value + 0)
            FROM $wpdb->postmeta
            WHERE meta_key = '_price'
        ");
        return $min_price ? floor($min_price) : 0;
    }
}

// Assurez-vous d'ajouter cette fonction en dehors de la classe du widget
// Modifiez la fonction AJAX pour prendre en compte les filtres sélectionnés
function update_dynamic_filters_elementor()
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

    $response = [];
    $query_args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'tax_query' => [],
    ];

    // Ajoutez les filtres sélectionnés à la requête
    foreach ($attributes as $attribute) {
        if (!empty($_GET[$attribute])) {
            $query_args['tax_query'][] = [
                'taxonomy' => 'car_' . $attribute,
                'field' => 'slug',
                'terms' => $_GET[$attribute],
            ];
        }
    }

    // Ajoutez le filtre de prix
    if (!empty($_GET['min_price']) && !empty($_GET['max_price'])) {
        $query_args['meta_query'][] = [
            'key' => '_price',
            'value' => [floatval($_GET['min_price']), floatval($_GET['max_price'])],
            'type' => 'NUMERIC',
            'compare' => 'BETWEEN',
        ];
    }

    // Exécutez la requête pour obtenir les produits filtrés
    $products_query = new WP_Query($query_args);
    $filtered_products = $products_query->posts;

    // Pour chaque attribut, obtenez les termes disponibles pour les produits filtrés
    foreach ($attributes as $attribute) {
        $available_terms = [];
        foreach ($filtered_products as $product) {
            $terms = get_the_terms($product->ID, 'car_' . $attribute);
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $available_terms[$term->slug] = $term->name;
                }
            }
        }
        $response[$attribute] = $available_terms;
    }

    // Ajoutez la plage de prix
    $response['price_range'] = [
        'min' => wc_get_min_price_of_filtered_products($filtered_products),
        'max' => wc_get_max_price_of_filtered_products($filtered_products),
    ];

    wp_send_json($response);
}
add_action('wp_ajax_update_dynamic_filters_elementor', 'update_dynamic_filters_elementor');
add_action('wp_ajax_nopriv_update_dynamic_filters_elementor', 'update_dynamic_filters_elementor');

// Fonction pour obtenir le prix minimum des produits filtrés
// Mettez à jour ces fonctions pour prendre en compte les produits filtrés
function wc_get_min_price_of_filtered_products($products)
{
    $min_price = PHP_INT_MAX;
    foreach ($products as $product) {
        $price = get_post_meta($product->ID, '_price', true);
        if ($price && $price < $min_price) {
            $min_price = $price;
        }
    }
    return $min_price !== PHP_INT_MAX ? floor($min_price) : 0;
}

function wc_get_max_price_of_filtered_products($products)
{
    $max_price = 0;
    foreach ($products as $product) {
        $price = get_post_meta($product->ID, '_price', true);
        if ($price && $price > $max_price) {
            $max_price = $price;
        }
    }
    return $max_price > 0 ? ceil($max_price) : 1000000;
}

// Enregistrez le widget avec Elementor
function register_improved_woocommerce_search_filter_widget($widgets_manager)
{
    require_once(__DIR__ . '/class-woocommerce-search-filter-widget.php');
    $widgets_manager->register_widget_type(new Improved_WooCommerce_Search_Filter_Widget());
}
add_action('elementor/widgets/widgets_registered', 'register_improved_woocommerce_search_filter_widget');

// Assurez-vous d'inclure les scripts et styles nécessaires
function enqueue_improved_woocommerce_search_filter_scripts()
{
    wp_enqueue_script('nouislider', 'https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.js', array('jquery'), '14.6.3', true);
    wp_enqueue_style('nouislider', 'https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.css', array(), '14.6.3');
}
add_action('wp_enqueue_scripts', 'enqueue_improved_woocommerce_search_filter_scripts');
