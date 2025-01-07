jQuery(document).ready(function($) {
    let searchTimeout;
    let pendingRequests = 0;
    let currentXHR = null;

    function createResultItem(result) {
        const imageUrl = result.image.medium ? result.image.medium.url :
            (result.image.thumbnail ? result.image.thumbnail.url : '');

        return `
            <a href="${result.url}" class="search-result-item">
                ${imageUrl ? `
                    <img src="${imageUrl}" alt="${result.title}" class="result-image">
                ` : ''}
                <div class="result-content">
                    <h3 class="result-title">${result.title}</h3>
                    <p class="result-excerpt">${result.excerpt}</p>
                    <div class="result-meta">
                        <span class="result-type">${result.post_type}</span>
                        <span class="result-similarity">${Math.round(result.similarity * 100)}% match</span>
                    </div>
                </div>
            </a>
        `;
    }

    function closeSearchResults($widget) {
        $widget.find('.search-results, .search-overlay').hide();
    }

    // Close results when clicking document
    $(document).on('click', function() {
        $('.openkbs-search-widget').each(function() {
            closeSearchResults($(this));
        });
    });

    // Prevent clicks within the results from closing the panel
    $('.openkbs-search-widget .search-results').on('click', function(event) {
        event.stopPropagation();
    });

    // Close button handler
    $('.openkbs-search-widget .close-results').on('click', function() {
        const $widget = $(this).closest('.openkbs-search-widget');
        closeSearchResults($widget);
    });

    // ESC key handler
    $(document).on('keyup', function(event) {
        if (event.key === 'Escape') {
            $('.openkbs-search-widget').each(function() {
                closeSearchResults($(this));
            });
        }
    });

    $('.openkbs-search-widget .search-input').on('input', function() {
        const input = $(this);
        const query = input.val().trim();
        const widget = input.closest('.openkbs-search-widget');
        const resultsContainer = widget.find('.results-container');
        const loadingSpinner = widget.find('.loading-spinner');
        const noResults = widget.find('.no-results');
        const searchResults = widget.find('.search-results');
        const searchOverlay = widget.find('.search-overlay');
        const itemTypes = input.data('item-types');

        // Cancel previous request if it exists
        if (currentXHR) {
            currentXHR.abort();
            pendingRequests = Math.max(0, pendingRequests - 1);
        }

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchResults.hide();
            searchOverlay.hide();
            return;
        }

        searchTimeout = setTimeout(() => {
            // Show loading state and overlay
            searchResults.show();
            searchOverlay.show();
            resultsContainer.empty();
            loadingSpinner.show();
            noResults.hide();

            const requestData = {
                query: query,
                limit: input.data('limit'),
                kbId: input.data('kb-id')
            };

            if (itemTypes && itemTypes.length > 0) {
                requestData.itemTypes = itemTypes;
            }

            pendingRequests++;

            currentXHR = $.ajax({
                url: openkbsSearch.ajaxUrl,
                method: 'GET',
                data: requestData,
                success: function(response) {
                    pendingRequests--;
                    if (pendingRequests === 0) {
                        loadingSpinner.hide();
                        if (response.success && response.results.length > 0) {
                            resultsContainer.empty();
                            response.results.forEach(result => {
                                resultsContainer.append(createResultItem(result));
                            });
                        } else {
                            noResults.show();
                            widget.find('.results-title').text('No results found');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    pendingRequests--;
                    if (pendingRequests === 0 && status !== 'abort') {
                        loadingSpinner.hide();
                        const msg = xhr?.status === 403
                            ? 'Public Search API Disabled.' +
                            'To enable it, please navigate to OpenKBS Search Settings'
                            : 'An error occurred while searching. Please try again later.'
                        resultsContainer.html(`<div class="error-message">${msg}</div>`);
                    }
                }
            });
        }, 300);
    });

    $('.openkbs-search-widget .search-button').click(function() {
        $(this).siblings('.search-input').trigger('input');
    });
});