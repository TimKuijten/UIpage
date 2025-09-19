(function() {
    function createButton(label, lang, controlsId) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'kls-switcher__button';
        button.dataset.lang = lang;
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-controls', controlsId);
        button.textContent = label;
        return button;
    }

    function collectSiblings(startNode) {
        var nodes = [];
        var node = startNode;
        while (node) {
            nodes.push(node);
            node = node.nextSibling;
        }
        return nodes;
    }

    function moveNodesIntoFragment(nodes) {
        var fragment = document.createDocumentFragment();
        nodes.forEach(function(node) {
            fragment.appendChild(node);
        });
        return fragment;
    }

    function activateLanguage(root, buttonGroup, lang) {
        root.dataset.activeLang = lang;
        document.documentElement.setAttribute('data-kls-lang', lang);

        var buttons = buttonGroup ? buttonGroup.querySelectorAll('.kls-switcher__button') : [];
        buttons.forEach(function(button) {
            var isActive = button.dataset.lang === lang;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            button.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        var panels = root.querySelectorAll('.kls-switcher__panel');
        panels.forEach(function(panel) {
            var isActive = panel.dataset.lang === lang;
            panel.classList.toggle('is-active', isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
    }

    function initializePortal() {
        var root = document.getElementById('kls-switcher-root');
        if (!root) {
            return;
        }

        if (root.dataset.initialized) {
            return;
        }

        var template = root.querySelector('template');
        if (!template) {
            return;
        }

        var siblings = collectSiblings(root.nextSibling);
        if (siblings.length === 0) {
            var bodyChildren = collectSiblings(document.body.firstChild);
            siblings = bodyChildren.filter(function(node) {
                return node !== root;
            });
        }

        var englishPanelId = 'kls-lang-en';
        var spanishPanelId = 'kls-lang-es';

        var buttonGroup = document.createElement('div');
        buttonGroup.className = 'kls-switcher__buttons';
        buttonGroup.setAttribute('role', 'tablist');
        buttonGroup.setAttribute('aria-label', root.dataset.label || 'Language selector');
        var controlsWrapper = document.createElement('div');
        controlsWrapper.className = 'kls-switcher';
        controlsWrapper.appendChild(buttonGroup);

        var panelsWrapper = document.createElement('div');
        panelsWrapper.className = 'kls-switcher__panels';

        var englishPanel = document.createElement('div');
        englishPanel.className = 'kls-switcher__panel';
        englishPanel.dataset.lang = 'en';
        englishPanel.id = englishPanelId;
        englishPanel.setAttribute('role', 'tabpanel');
        englishPanel.appendChild(moveNodesIntoFragment(siblings));

        var spanishPanel = document.createElement('div');
        spanishPanel.className = 'kls-switcher__panel';
        spanishPanel.dataset.lang = 'es';
        spanishPanel.id = spanishPanelId;
        spanishPanel.setAttribute('role', 'tabpanel');
        spanishPanel.innerHTML = template.innerHTML;

        template.remove();

        panelsWrapper.appendChild(englishPanel);
        panelsWrapper.appendChild(spanishPanel);

        var englishButton = createButton(root.dataset.englishLabel || 'English', 'en', englishPanelId);
        var spanishButton = createButton(root.dataset.spanishLabel || 'Español', 'es', spanishPanelId);

        buttonGroup.appendChild(englishButton);
        buttonGroup.appendChild(spanishButton);

        var navCta = document.querySelector('.nav-cta');
        if (navCta) {
            controlsWrapper.classList.add('kls-switcher--nav');
            var linkedIn = navCta.querySelector('.nav-linkedin');
            if (linkedIn && linkedIn.parentNode === navCta) {
                linkedIn.insertAdjacentElement('beforebegin', controlsWrapper);
            } else {
                navCta.appendChild(controlsWrapper);
            }
        } else {
            controlsWrapper.classList.add('kls-switcher--fallback');
            root.appendChild(controlsWrapper);
        }

        root.appendChild(panelsWrapper);

        buttonGroup.addEventListener('click', function(event) {
            var target = event.target instanceof HTMLElement ? event.target.closest('.kls-switcher__button') : null;
            if (!target) {
                return;
            }

            event.preventDefault();
            var lang = target.dataset.lang;
            if (!lang) {
                return;
            }

            activateLanguage(root, buttonGroup, lang);
        });

        buttonGroup.addEventListener('keydown', function(event) {
            var target = event.target instanceof HTMLElement ? event.target.closest('.kls-switcher__button') : null;
            if (!target) {
                return;
            }

            var key = event.key;
            if (key !== 'ArrowRight' && key !== 'ArrowLeft') {
                return;
            }

            event.preventDefault();
            var buttons = Array.prototype.slice.call(buttonGroup.querySelectorAll('.kls-switcher__button'));
            var index = buttons.indexOf(target);
            if (index === -1) {
                return;
            }

            var offset = key === 'ArrowRight' ? 1 : -1;
            var nextIndex = (index + offset + buttons.length) % buttons.length;
            var nextButton = buttons[nextIndex];
            nextButton.focus();
            if (nextButton.dataset.lang) {
                activateLanguage(root, buttonGroup, nextButton.dataset.lang);
            }
        });

        root.dataset.initialized = '1';

        var defaultLang = root.dataset.default || 'en';
        if (defaultLang !== 'en' && defaultLang !== 'es') {
            defaultLang = 'en';
        }

        activateLanguage(root, buttonGroup, defaultLang);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePortal);
    } else {
        initializePortal();
    }
})();
