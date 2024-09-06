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
?>
        <div class="woocommerce-search-filter-widget">
            <form action="<?php echo esc_url(home_url('/')); ?>" method="get" id="search-filter-form">
                <input type="hidden" name="post_type" value="product">

                <?php if ($settings['product_type'] !== 'all'): ?>
                    <input type="hidden" name="product_type" value="<?php echo esc_attr($settings['product_type']); ?>">
                <?php endif; ?>

                <?php if ($settings['sale_rental_type'] !== 'all'): ?>
                    <input type="hidden" name="sale_rental_type" value="<?php echo esc_attr($settings['sale_rental_type']); ?>">
                <?php endif; ?>

                <?php if (!empty($settings['product_categories'])): ?>
                    <input type="hidden" name="product_cat" value="<?php echo esc_attr(implode(',', $settings['product_categories'])); ?>">
                <?php endif; ?>

                <div class="filter-container">
                    <div class="filter-grid">
                        <?php foreach ($attributes as $attribute): ?>
                            <?php
                            $terms = get_terms([
                                'taxonomy' => 'car_' . $attribute,
                                'hide_empty' => false,
                            ]);
                            if (!empty($terms) && !is_wp_error($terms)):
                            ?>
                                <select name="<?php echo esc_attr($attribute); ?>" class="dynamic-filter">
                                    <option value=""><?php echo esc_html($this->get_translated_attribute_label($attribute)); ?></option>
                                    <?php foreach ($terms as $term): ?>
                                        <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="price-filter">
                        <label for="price-range-slider"><?php _e('Prix', 'text-domain'); ?></label>
                        <div id="price-range-slider"></div>
                        <div class="price-inputs">
                            <input type="number" id="min_price" name="min_price" value="<?php echo esc_attr($min_price); ?>" min="<?php echo esc_attr($min_price); ?>" max="<?php echo esc_attr($max_price); ?>">
                            <input type="number" id="max_price" name="max_price" value="<?php echo esc_attr($max_price); ?>" min="<?php echo esc_attr($min_price); ?>" max="<?php echo esc_attr($max_price); ?>">
                        </div>
                    </div>
                    <button type="submit" class="search-button"><?php _e('Rechercher', 'text-domain'); ?></button>
                </div>
            </form>
        </div>
        <script>
            jQuery(document).ready(function($) {
                var priceSlider = document.getElementById('price-range-slider');
                var minPrice = <?php echo $min_price; ?>;
                var maxPrice = <?php echo $max_price; ?>;

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

                function updateFilters() {
                    var formData = $('#search-filter-form').serialize();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: formData + '&action=update_dynamic_filters_elementor',
                        success: function(response) {
                            $.each(response, function(attribute, options) {
                                var select = $('select[name="' + attribute + '"]');
                                select.empty();
                                select.append($('<option>', {
                                    value: '',
                                    text: select.attr('placeholder')
                                }));
                                $.each(options, function(value, label) {
                                    select.append($('<option>', {
                                        value: value,
                                        text: label
                                    }));
                                });
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

                $('.dynamic-filter').change(updateFilters);
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
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }

            .price-filter {
                padding: 0 10px;
            }

            select,
            input[type="number"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }

            .price-inputs {
                display: flex;
                justify-content: space-between;
                margin-top: 10px;
            }

            .price-inputs input[type="number"] {
                width: 45%;
            }

            .search-button {
                padding: 10px 20px;
                background-color: #0073aa;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                align-self: flex-start;
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
        </style>
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
