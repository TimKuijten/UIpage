(function() {
    if (!window.KLSData || !document.body) {
        return;
    }

    var settings = window.KLSData;
    var originalHtmlLang = document.documentElement.getAttribute('lang') || settings.htmlLang || 'en';
    var translations = settings.translations || {};
    var normalizedTranslations = {};
    var hasTranslations = false;

    function normalize(value) {
        return value.replace(/\s+/g, ' ').trim();
    }

    Object.keys(translations).forEach(function(key) {
        var normalizedKey = normalize(String(key));
        if (!normalizedKey) {
            return;
        }

        normalizedTranslations[normalizedKey] = String(translations[key]);
        hasTranslations = true;
    });

    if (!hasTranslations) {
        return;
    }

    var language = (settings.defaultLanguage === 'es') ? 'es' : 'en';
    var textNodes = [];

    function collectTextNodes() {
        var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
            acceptNode: function(node) {
                if (!node || !node.nodeValue) {
                    return NodeFilter.FILTER_REJECT;
                }

                var parent = node.parentElement;
                if (!parent) {
                    return NodeFilter.FILTER_REJECT;
                }

                if (parent.closest && parent.closest('.kls-switcher')) {
                    return NodeFilter.FILTER_REJECT;
                }

                var tag = parent.tagName ? parent.tagName.toLowerCase() : '';
                if (tag === 'script' || tag === 'style' || tag === 'noscript' || tag === 'template') {
                    return NodeFilter.FILTER_REJECT;
                }

                if (!node.nodeValue.trim()) {
                    return NodeFilter.FILTER_REJECT;
                }

                return NodeFilter.FILTER_ACCEPT;
            }
        });

        var current;
        while ((current = walker.nextNode())) {
            var value = current.nodeValue;
            var normalized = normalize(value);
            if (!normalized) {
                continue;
            }

            var translation = normalizedTranslations[normalized];
            if (!translation) {
                continue;
            }

            var leading = value.match(/^\s*/);
            var trailing = value.match(/\s*$/);

            textNodes.push({
                node: current,
                normalized: normalized,
                original: value,
                translation: translation,
                leading: leading ? leading[0] : '',
                trailing: trailing ? trailing[0] : ''
            });
        }
    }

    function setLanguage(nextLanguage) {
        if (language === nextLanguage) {
            return;
        }

        language = nextLanguage;
        updateLanguage();
    }

    function updateLanguage() {
        var useSpanish = language === 'es';

        textNodes.forEach(function(entry) {
            if (!entry.node || !entry.node.nodeValue) {
                return;
            }

            if (useSpanish) {
                entry.node.nodeValue = entry.leading + entry.translation + entry.trailing;
            } else {
                entry.node.nodeValue = entry.original;
            }
        });

        if (language === 'es') {
            document.documentElement.setAttribute('lang', 'es');
        } else {
            document.documentElement.setAttribute('lang', originalHtmlLang);
        }

        updateButtonStates();
    }

    var switcherContainer;
    var englishButton;
    var spanishButton;

    function updateButtonStates() {
        if (!englishButton || !spanishButton) {
            return;
        }

        var activeClass = 'kls-switcher__button--active';
        if (language === 'es') {
            englishButton.classList.remove(activeClass);
            spanishButton.classList.add(activeClass);
            englishButton.setAttribute('aria-pressed', 'false');
            spanishButton.setAttribute('aria-pressed', 'true');
        } else {
            spanishButton.classList.remove(activeClass);
            englishButton.classList.add(activeClass);
            spanishButton.setAttribute('aria-pressed', 'false');
            englishButton.setAttribute('aria-pressed', 'true');
        }
    }

    function createSwitcher() {
        switcherContainer = document.createElement('div');
        switcherContainer.className = 'kls-switcher';
        switcherContainer.setAttribute('role', 'group');
        switcherContainer.setAttribute('aria-label', 'Language selector');

        englishButton = document.createElement('button');
        englishButton.type = 'button';
        englishButton.className = 'kls-switcher__button';
        englishButton.textContent = settings.englishLabel || 'English';
        englishButton.addEventListener('click', function() {
            setLanguage('en');
        });

        spanishButton = document.createElement('button');
        spanishButton.type = 'button';
        spanishButton.className = 'kls-switcher__button';
        spanishButton.textContent = settings.spanishLabel || 'Español';
        spanishButton.addEventListener('click', function() {
            setLanguage('es');
        });

        switcherContainer.appendChild(englishButton);
        switcherContainer.appendChild(spanishButton);

        var fallback = document.getElementById('kls-switcher-root');
        if (fallback) {
            fallback.removeAttribute('hidden');
            fallback.appendChild(switcherContainer);
        }

        injectIntoNavigation();
        updateButtonStates();
    }

    function injectIntoNavigation() {
        var navigation = document.querySelector('#primary-menu, nav .primary-menu, nav[aria-label*="Primary" i] .menu, nav[aria-label*="Primary" i], .primary-menu');
        if (!navigation) {
            if (switcherContainer) {
                switcherContainer.classList.remove('kls-switcher--nav');
            }
            return;
        }

        switcherContainer.classList.add('kls-switcher--nav');

        var linkedInLink = navigation.querySelector('a[href*="linkedin.com" i]');
        var linkedInItem = linkedInLink ? linkedInLink.closest('li') : null;
        var containerWrapper;

        if (linkedInItem && linkedInItem.parentNode) {
            containerWrapper = document.createElement(linkedInItem.nodeName || 'li');
            containerWrapper.className = (linkedInItem.className ? linkedInItem.className + ' ' : '') + 'kls-switcher__item';
            containerWrapper.appendChild(switcherContainer);
            linkedInItem.parentNode.insertBefore(containerWrapper, linkedInItem);
        } else if (navigation.tagName && navigation.tagName.toLowerCase() === 'ul') {
            containerWrapper = document.createElement('li');
            containerWrapper.className = 'menu-item kls-switcher__item';
            containerWrapper.appendChild(switcherContainer);
            navigation.appendChild(containerWrapper);
        } else {
            navigation.appendChild(switcherContainer);
        }
    }

    function init() {
        collectTextNodes();
        if (!textNodes.length) {
            return;
        }

        createSwitcher();
        updateLanguage();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
