(function() {
    function activate(panelGroup, lang) {
        panelGroup.querySelectorAll('.kls-switcher__panel').forEach(function(panel) {
            if (panel.dataset.lang === lang) {
                panel.classList.add('is-active');
                panel.setAttribute('aria-hidden', 'false');
            } else {
                panel.classList.remove('is-active');
                panel.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function updateButtons(buttonGroup, lang) {
        buttonGroup.querySelectorAll('.kls-switcher__button').forEach(function(button) {
            var isActive = button.dataset.lang === lang;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            button.setAttribute('tabindex', isActive ? '0' : '-1');
        });
    }

    function initSwitcher(container) {
        var buttonGroup = container.querySelector('.kls-switcher__buttons');
        var panelGroup = container.querySelector('.kls-switcher__panels');

        if (!buttonGroup || !panelGroup) {
            return;
        }

        buttonGroup.addEventListener('click', function(event) {
            if (!(event.target instanceof HTMLElement)) {
                return;
            }

            var button = event.target.closest('.kls-switcher__button');
            if (!button) {
                return;
            }

            var lang = button.dataset.lang;
            if (!lang) {
                return;
            }

            event.preventDefault();
            updateButtons(buttonGroup, lang);
            activate(panelGroup, lang);
        });

        var defaultLang = container.dataset.default || 'en';
        updateButtons(buttonGroup, defaultLang);
        activate(panelGroup, defaultLang);
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.kls-switcher').forEach(initSwitcher);
    });
})();
