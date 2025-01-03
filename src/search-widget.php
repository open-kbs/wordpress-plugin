<?php
function openkbs_enqueue_search_widget_assets() {
    wp_enqueue_style(
        'openkbs-search-widget',
        plugins_url('src/assets/css/search-widget.css', dirname(__FILE__))
    );

    wp_enqueue_script(
        'openkbs-search-widget',
        plugins_url('js/search-widget.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script('openkbs-search-widget', 'openkbsSearch', array(
        'ajaxUrl' => rest_url('openkbs/v1/search-public'),
        'nonce' => wp_create_nonce('wp_rest'),
    ));
}

function openkbs_get_search_widget_html($atts) {
    $atts = shortcode_atts(array(
        'placeholder' => 'Search...',
        'limit' => 10,
        'kb_id' => '',
        'item_types' => '',
    ), $atts);

    $item_types = array_filter(array_map('trim', explode(',', $atts['item_types'])));

    ob_start();
    ?>
    <div class="openkbs-search-widget">
        <div class="search-container">
            <input type="text"
                   class="search-input"
                   placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                   data-limit="<?php echo esc_attr($atts['limit']); ?>"
                   data-kb-id="<?php echo esc_attr($atts['kb_id']); ?>"
                   data-item-types="<?php echo esc_attr(json_encode($item_types)); ?>">
            <button class="search-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </div>
        <div class="search-overlay"></div>
        <div class="search-results" style="display: none;">
            <div class="search-results-header">
                <h3 class="results-title">Search Results</h3>
                <button class="close-results" aria-label="Close search results">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="results-container"></div>
            <div class="loading-spinner" style="display: none;">
                <div class="spinner"></div>
            </div>
            <div class="no-results" style="display: none;">
                No results found
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}