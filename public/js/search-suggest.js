(function () {
    var DEBOUNCE_MS = 250;
    var MIN_QUERY_LENGTH = 2;

    document.querySelectorAll('[data-search-suggest]').forEach(function (wrapper) {
        var input = wrapper.querySelector('input[type="search"]');
        var url = wrapper.getAttribute('data-search-suggest');
        if (!input || !url) {
            return;
        }

        var results = document.createElement('div');
        results.className = 'search-suggest-results';
        wrapper.appendChild(results);

        var debounceTimer = null;
        var abortController = null;
        var activeIndex = -1;
        var items = [];

        function escapeHtml(value) {
            var div = document.createElement('div');
            div.textContent = value;
            return div.innerHTML;
        }

        function highlight(label, query) {
            var index = label.toLowerCase().indexOf(query.toLowerCase());
            if (index === -1) {
                return escapeHtml(label);
            }
            var before = escapeHtml(label.slice(0, index));
            var match = escapeHtml(label.slice(index, index + query.length));
            var after = escapeHtml(label.slice(index + query.length));
            return before + '<mark>' + match + '</mark>' + after;
        }

        function close() {
            results.innerHTML = '';
            results.classList.remove('is-open');
            items = [];
            activeIndex = -1;
        }

        function setActive(index) {
            var links = results.querySelectorAll('.search-suggest-item');
            links.forEach(function (link, i) {
                link.classList.toggle('is-active', i === index);
            });
            activeIndex = index;
        }

        function render(suggestions, query) {
            items = suggestions;
            if (!items.length) {
                close();
                return;
            }

            results.innerHTML = items.map(function (item) {
                return '<a class="search-suggest-item" href="' + item.url + '">' + highlight(item.label, query) + '</a>';
            }).join('');
            results.classList.add('is-open');
            activeIndex = -1;
        }

        function fetchSuggestions(query) {
            if (abortController) {
                abortController.abort();
            }
            abortController = new AbortController();

            fetch(url + '?q=' + encodeURIComponent(query), {
                signal: abortController.signal,
                headers: { Accept: 'application/json' },
            })
                .then(function (response) {
                    return response.ok ? response.json() : [];
                })
                .then(function (suggestions) {
                    render(suggestions, query);
                })
                .catch(function (error) {
                    if (error.name !== 'AbortError') {
                        close();
                    }
                });
        }

        input.addEventListener('input', function () {
            var query = input.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < MIN_QUERY_LENGTH) {
                close();
                return;
            }

            debounceTimer = setTimeout(function () {
                fetchSuggestions(query);
            }, DEBOUNCE_MS);
        });

        input.addEventListener('keydown', function (event) {
            if (!items.length) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive((activeIndex + 1) % items.length);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive((activeIndex - 1 + items.length) % items.length);
            } else if (event.key === 'Enter') {
                if (activeIndex > -1) {
                    event.preventDefault();
                    window.location.href = items[activeIndex].url;
                }
            } else if (event.key === 'Escape') {
                close();
            }
        });

        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target)) {
                close();
            }
        });
    });
})();
