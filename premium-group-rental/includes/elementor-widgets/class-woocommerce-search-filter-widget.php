<?php
class Improved_WooCommerce_Search_Filter_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'improved_woocommerce_search_filter';
    }

    public function get_title()
    {
        return __('Recherche & Filtrage WooCommerce Amélioré', 'text-domain');
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
            'product_type',
            [
                'label' => __('Type de produit', 'text-domain'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all' => __('Tous', 'text-domain'),
                    'sale' => __('À vendre', 'text-domain'),
                    'rental' => __('À louer', 'text-domain'),
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

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $attributes = $this->get_car_attributes();
        $max_price = $this->get_max_product_price();
?>
        <div class="woocommerce-search-filter-widget">
            <form action="<?php echo esc_url(home_url('/')); ?>" method="get" id="search-filter-form">
                <input type="hidden" name="post_type" value="product">

                <?php if ($settings['product_type'] !== 'all'): ?>
                    <input type="hidden" name="product_type" value="<?php echo esc_attr($settings['product_type']); ?>">
                <?php endif; ?>

                <?php if (!empty($settings['product_categories'])): ?>
                    <input type="hidden" name="product_cat" value="<?php echo esc_attr(implode(',', $settings['product_categories'])); ?>">
                <?php endif; ?>

                <div class="filter-container">
                    <div class="filter-grid">
                        <?php foreach ($attributes as $attribute): ?>
                            <select name="<?php echo esc_attr($attribute); ?>" class="dynamic-filter">
                                <option value=""><?php echo esc_html(ucfirst($attribute)); ?></option>
                            </select>
                        <?php endforeach; ?>
                    </div>
                    <div class="price-filter">
                        <div id="price-range-slider"></div>
                        <input type="hidden" name="min_price" id="min_price" value="0">
                        <input type="hidden" name="max_price" id="max_price" value="<?php echo esc_attr($max_price); ?>">
                    </div>
                    <button type="submit" class="search-button"><?php _e('Rechercher', 'text-domain'); ?></button>
                </div>
            </form>
        </div>
        <script>
            jQuery(document).ready(function($) {
                var priceSlider = document.getElementById('price-range-slider');
                var maxPrice = <?php echo $max_price; ?>;

                noUiSlider.create(priceSlider, {
                    start: [0, maxPrice],
                    connect: true,
                    range: {
                        'min': 0,
                        'max': maxPrice
                    }
                });

                priceSlider.noUiSlider.on('update', function(values, handle) {
                    $('#min_price').val(Math.round(values[0]));
                    $('#max_price').val(Math.round(values[1]));
                });

                function updateFilters() {
                    var formData = $('#search-filter-form').serialize();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: formData + '&action=update_dynamic_filters',
                        success: function(response) {
                            $.each(response, function(attribute, options) {
                                var select = $('select[name="' + attribute + '"]');
                                select.empty();
                                select.append($('<option>', {
                                    value: '',
                                    text: ucfirst(attribute)
                                }));
                                $.each(options, function(value, label) {
                                    select.append($('<option>', {
                                        value: value,
                                        text: label
                                    }));
                                });
                            });

                            // Update price slider
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

                $('.dynamic-filter').change(updateFilters);
                updateFilters(); // Initial update

                function ucfirst(string) {
                    return string.charAt(0).toUpperCase() + string.slice(1);
                }
            });
        </script>
        <style>
            .woocommerce-search-filter-widget {
                background-color: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 20px;
            }

            .filter-container {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .filter-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .price-filter {
                padding: 0 10px;
            }

            select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .search-button {
                padding: 10px 20px;
                background-color: #0073aa;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                align-self: flex-end;
            }

            .search-button:hover {
                background-color: #005177;
            }
        </style>
<?php
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
    }

    private function get_max_product_price()
    {
        global $wpdb;
        $max_price = $wpdb->get_var("
            SELECT MAX(meta_value + 0)
            FROM $wpdb->postmeta
            WHERE meta_key = '_price'
        ");
        return $max_price ? ceil($max_price) : 1000000; // Fallback to 1,000,000 if no products found
    }
}

// Add this function to your theme's functions.php or in a separate plugin file
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

    $response = [];

    // Get current filter values
    $current_filters = [];
    foreach ($attributes as $attribute) {
        if (isset($_GET[$attribute])) {
            $current_filters[$attribute] = sanitize_text_field($_GET[$attribute]);
        }
    }

    // Get filtered product IDs
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [],
    ];

    foreach ($current_filters as $taxonomy => $value) {
        $args['tax_query'][] = [
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => $value,
        ];
    }

    $product_ids = get_posts($args);

    // Get available options for each attribute
    foreach ($attributes as $attribute) {
        $terms = get_terms([
            'taxonomy' => $attribute,
            'hide_empty' => true,
            'object_ids' => $product_ids,
        ]);

        $options = [];
        foreach ($terms as $term) {
            $options[$term->slug] = $term->name;
        }

        $response[$attribute] = $options;
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
