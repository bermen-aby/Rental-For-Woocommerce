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

    public function get_script_depends()
    {
        return ['nouislider', 'improved-woocommerce-search-filter'];
    }

    public function get_style_depends()
    {
        return ['nouislider'];
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

        $this->add_control(
            'car_attributes',
            [
                'label' => __('Attributs de véhicule à afficher', 'text-domain'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'options' => $this->get_car_attributes(),
                'multiple' => true,
                'default' => array_keys($this->get_car_attributes()),
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $attributes = $settings['car_attributes'];
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
                            <div class="filter-item">
                                <label for="<?php echo esc_attr($attribute); ?>"><?php echo esc_html(ucfirst($attribute)); ?></label>
                                <select name="<?php echo esc_attr($attribute); ?>" id="<?php echo esc_attr($attribute); ?>" class="dynamic-filter">
                                    <option value=""><?php echo esc_html(ucfirst($attribute)); ?></option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="price-filter">
                        <label for="price-range-slider"><?php _e('Fourchette de prix', 'text-domain'); ?></label>
                        <div id="price-range-slider"></div>
                        <div class="price-inputs">
                            <input type="number" id="min_price" name="min_price" readonly>
                            <input type="number" id="max_price" name="max_price" readonly>
                        </div>
                    </div>
                    <button type="submit" class="search-button"><?php _e('Rechercher', 'text-domain'); ?></button>
                </div>
            </form>
        </div>
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
        return $max_price ? ceil($max_price) : 1000000; // Fallback to 1,000,000 if no products found
    }
}
