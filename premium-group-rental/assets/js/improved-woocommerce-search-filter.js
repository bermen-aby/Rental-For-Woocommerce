jQuery(document).ready(function($) {
    var priceSlider = document.getElementById('price-range-slider');
    var minPriceInput = document.getElementById('min_price');
    var maxPriceInput = document.getElementById('max_price');
    var maxPrice = <?php echo $this->get_max_product_price(); ?>;

    noUiSlider.create(priceSlider, {
        start: [0, maxPrice],
        connect: true,
        range: {
            'min': 0,
            'max': maxPrice
        },
        format: {
            to: function (value) {
                return Math.round(value);
            },
            from: function (value) {
                return Number(value);
            }
        }
    });

    priceSlider.noUiSlider.on('update', function(values, handle) {
        var value = values[handle];
        if (handle) {
            maxPriceInput.value = value;
        } else {
            minPriceInput.value = value;
        }
    });

    function updateFilters() {
        var formData = $('#search-filter-form').serialize();
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            data: formData + '&action=update_dynamic_filters',
            success: function(response) {
                $.each(response.attributes, function(attribute, options) {
                    var select = $('select[name="' + attribute + '"]');
                    var currentValue = select.val();
                    select.empty();
                    select.append($('<option>', {
                        value: '',
                        text: ucfirst(attribute)
                    }));
                    $.each(options, function(value, label) {
                        select.append($('<option>', {
                            value: value,
                            text: label,
                            selected: (value == currentValue)
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
    updateFilters(); // Initial update

    function ucfirst(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
});