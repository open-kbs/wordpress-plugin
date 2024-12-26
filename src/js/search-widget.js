jQuery(document).ready(function($) {
    let searchTimeout;

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

    $('.openkbs-search-widget .search-input').on('input', function() {
        const input = $(this);
        const query = input.val().trim();
        const widget = input.closest('.openkbs-search-widget');
        const resultsContainer = widget.find('.results-container');
        const loadingSpinner = widget.find('.loading-spinner');
        const noResults = widget.find('.no-results');
        const searchResults = widget.find('.search-results');

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchResults.hide();
            return;
        }

        searchTimeout = setTimeout(() => {
            // Show loading state
            searchResults.show();
            resultsContainer.empty();
            loadingSpinner.show();
            noResults.hide();

            $.ajax({
                url: openkbsSearch.ajaxUrl,
                method: 'GET',
                data: {
                    query: query,
                    limit: input.data('limit'),
                    kbId: input.data('kb-id')
                },
                success: function(response) {
                    loadingSpinner.hide();

                    if (response.success && response.results.length > 0) {
                        resultsContainer.empty();
                        response.results.forEach(result => {
                            resultsContainer.append(createResultItem(result));
                        });
                    } else {
                        noResults.show();
                    }
                },
                error: function(xhr) {
                    loadingSpinner.hide();
                    resultsContainer.html(`
                        <div class="error-message">
                            An error occurred while searching. Please try again later.
                        </div>
                    `);
                }
            });
        }, 300);
    });

    $('.openkbs-search-widget .search-button').click(function() {
        $(this).siblings('.search-input').trigger('input');
    });
});