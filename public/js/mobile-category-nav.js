(function () {
    var mainNav = document.getElementById('mainNav');
    var mcn = mainNav ? mainNav.querySelector('[data-mcn]') : null;

    if (!mainNav || !mcn) {
        return;
    }

    var siblings = Array.prototype.filter.call(mainNav.children, function (el) {
        return el !== mcn;
    });

    var stack = [];

    function showPanel(panelId) {
        mcn.querySelectorAll('.mcn-panel').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.dataset.mcnPanel === panelId);
        });
    }

    function openDrillDown(panelId) {
        siblings.forEach(function (el) {
            el.classList.add('mcn-hidden');
        });
        mcn.classList.add('is-active');
        showPanel(panelId);
    }

    function closeDrillDown() {
        siblings.forEach(function (el) {
            el.classList.remove('mcn-hidden');
        });
        mcn.classList.remove('is-active');
        stack = [];
    }

    mainNav.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-mcn-open]');
        if (opener) {
            var current = mcn.querySelector('.mcn-panel.is-active');
            if (current) {
                stack.push(current.dataset.mcnPanel);
            }
            if (!mcn.classList.contains('is-active')) {
                openDrillDown(opener.getAttribute('data-mcn-open'));
            } else {
                showPanel(opener.getAttribute('data-mcn-open'));
            }
            return;
        }

        var back = event.target.closest('[data-mcn-back]');
        if (back) {
            var previous = stack.pop();
            if (previous) {
                showPanel(previous);
            } else {
                closeDrillDown();
            }
        }
    });
})();
