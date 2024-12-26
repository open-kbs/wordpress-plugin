<?php
if (!defined('ABSPATH')) exit;

function openkbs_render_search_settings() {
    ?>
    <div class="search-test-interface" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccc;">
        <h3>Search Settings</h3>

        <div style="max-width: 800px;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Select Knowledge Base</label>
                <select id="search-kb-select" style="width: 100%; max-width: 400px;">
                    <?php
                    $apps = openkbs_get_apps();
                    foreach ($apps as $app_id => $app) {
                        if (isset($app['semantic_search']['enabled']) && $app['semantic_search']['enabled'] === 'on') {
                            echo '<option value="' . esc_attr($app_id) . '" ' .
                                 'data-api-key="' . esc_attr($app['wpapiKey']) . '">' .
                                 esc_html($app['kbTitle']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Search Query</label>
                <input type="text" id="search-query" style="width: 100%;  max-width: 400px;" placeholder="Enter your search query">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Results Limit</label>
                <input type="number" id="search-limit" value="10" min="1" max="100" style="width: 100px;">
            </div>

            <button type="button" id="run-search" class="button button-primary">Run Search</button>

            <div id="search-results" style="margin-top: 20px; display: none;">
                <h4>Search Results</h4>
                <div id="results-container"></div>
            </div>
        </div>

        <br />
        <div class="public-search-settings" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccc;">
            <h3>Public Search API</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Public Search API</th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="public-search-toggle"
                                <?php checked(get_option('openkbs_public_search_enabled'), true); ?>>
                            <span class="slider round"></span>
                        </label>
                        <div class="security-notice" style="margin-top: 15px; padding: 15px; background: #fff8e5; border-left: 4px solid #ffb900;">
                            <h4 style="margin-top: 0; color: #826200;">ℹ️ Public Access Notice</h4>
                            <p style="margin-bottom: 10px;">
                                This setting enables a public search endpoint that can be accessed without authentication.
                                When enabled, anyone can search your public content through the API at:
                            </p>
                            <code style="display: block; padding: 10px; background: #f7f7f7; margin: 10px 0;">
                                <?php echo esc_url(rest_url('openkbs/v1/search-public')); ?>
                            </code>
                            <p style="margin-bottom: 0; color: #826200;">
                                Only enable this if you want to allow public access to your semantic search functionality.
                            </p>
                        </div>
                        <div id="public-search-status-message" style="display: none; margin-top: 10px; padding: 10px; border-radius: 4px;"></div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
        <script>
            jQuery(document).ready(function($) {
                // Add this to the existing jQuery document ready function
                $('#public-search-toggle').change(function() {
                    const isEnabled = $(this).is(':checked');
                    const statusMessage = $('#public-search-status-message');

                    // Show loading state
                    $(this).prop('disabled', true);
                    statusMessage.html('Saving...').css({
                        'display': 'block',
                        'background-color': '#f0f0f0',
                        'color': '#666'
                    });

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'toggle_public_search',
                            enabled: isEnabled,
                            nonce: '<?php echo wp_create_nonce("public_search_toggle"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                statusMessage.html('Settings saved successfully!').css({
                                    'background-color': '#dff0d8',
                                    'color': '#3c763d'
                                });
                            } else {
                                statusMessage.html('Error: ' + response.data).css({
                                    'background-color': '#f2dede',
                                    'color': '#a94442'
                                });
                                $('#public-search-toggle').prop('checked', !isEnabled);
                            }
                        },
                        error: function() {
                            statusMessage.html('Network error occurred').css({
                                'background-color': '#f2dede',
                                'color': '#a94442'
                            });
                            $('#public-search-toggle').prop('checked', !isEnabled);
                        },
                        complete: function() {
                            $('#public-search-toggle').prop('disabled', false);
                            setTimeout(function() {
                                statusMessage.fadeOut();
                            }, 3000);
                        }
                    });
                });

                $('#run-search').click(function() {
                    const button = $(this);
                    const resultsDiv = $('#search-results');
                    const resultsContainer = $('#results-container');

                    // Get search parameters
                    const query = $('#search-query').val();
                    const kbId = $('#search-kb-select').val();
                    const limit = $('#search-limit').val();

                    if (!query) {
                        alert('Please enter a search query');
                        return;
                    }

                    // Get the API key for the selected KB
                    const apiKey = $('#search-kb-select option:selected').data('api-key');

                    if (!apiKey) {
                        alert('No API key found for the selected knowledge base');
                        return;
                    }

                    // Show loading state
                    button.prop('disabled', true).text('Searching...');
                    resultsContainer.html('<div class="spinner is-active" style="float: none; margin: 0;"></div>');
                    resultsDiv.show();

                    // Make API request
                    $.ajax({
                        url: '<?php echo esc_url_raw(rest_url('openkbs/v1/search')); ?>',
                        method: 'GET',
                        data: {
                            query: query,
                            kbId: kbId,
                            limit: limit
                        },
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('WP-API-KEY', apiKey);
                        },
                        success: function(response) {
                            if (response.success && response.results) {
                                // Clear previous results
                                resultsContainer.empty();

                                if (response.results.length === 0) {
                                    resultsContainer.html('<p>No results found.</p>');
                                    return;
                                }

                                // Create results table
                                const table = $('<table class="wp-list-table widefat fixed striped">').appendTo(resultsContainer);

                                // Add table header
                                table.append(`
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">Score</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th style="width: 100px;">Action</th>
                                        </tr>
                                    </thead>
                                `);

                                // Add results
                                const tbody = $('<tbody>').appendTo(table);
                                response.results.forEach(function(result) {
                                    const score = (result.similarity * 100).toFixed(2);
                                    const row = $('<tr>').appendTo(tbody);

                                    row.append(`
                                        <td>${score}%</td>
                                        <td>${result.title}</td>
                                        <td>${result.post_type}</td>
                                        <td>
                                            <a href="${result.url}" target="_blank" class="button button-small">View</a>
                                        </td>
                                    `);
                                });
                            } else {
                                resultsContainer.html('<p>Error: ' + (response.message || 'Unknown error occurred') + '</p>');
                            }
                        },
                        error: function(xhr, status, error) {
                            let errorMessage = error;
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage = response.message || error;
                            } catch (e) {}
                            resultsContainer.html('<p class="error" style="color: #dc3232;">Error: ' + errorMessage + '</p>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('Run Search');
                        }
                    });
                });

                // Allow Enter key to trigger search
                $('#search-query').keypress(function(e) {
                    if (e.which === 13) {
                        $('#run-search').click();
                    }
                });

            });
        </script>
    <?php
}