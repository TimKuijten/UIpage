(function() {
    if (!window.KLSData || !document.body) {
        return;
    }

    var settings = window.KLSData;
    var originalHtmlLang = document.documentElement.getAttribute('lang') || settings.htmlLang || 'en';
    var translations = settings.translations || {};
    var normalizedTranslations = {};
    var hasTranslations = false;
    var STORAGE_KEY = 'klsPreferredLanguage';

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

    function getStoredLanguage() {
        try {
            return window.localStorage.getItem(STORAGE_KEY);
        } catch (error) {
            return null;
        }
    }

    function persistLanguage(value) {
        try {
            window.localStorage.setItem(STORAGE_KEY, value);
        } catch (error) {
            /* noop */
        }
    }

    var storedLanguage = getStoredLanguage();
    if (storedLanguage === 'es' || storedLanguage === 'en') {
        language = storedLanguage;
    }

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
        if (nextLanguage !== 'en' && nextLanguage !== 'es') {
            return;
        }

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
        persistLanguage(language);
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

    function findSwitcherElements() {
        switcherContainer = document.querySelector('.kls-switcher');
        if (!switcherContainer) {
            var portal = document.getElementById('kls-switcher-root');
            if (portal) {
                portal.removeAttribute('hidden');
                switcherContainer = portal.querySelector('.kls-switcher');
            }
        }

        if (!switcherContainer) {
            return false;
        }

        englishButton = switcherContainer.querySelector('[data-language="en"]');
        spanishButton = switcherContainer.querySelector('[data-language="es"]');

        if (!englishButton || !spanishButton) {
            return false;
        }

        englishButton.addEventListener('click', function() {
            setLanguage('en');
        });

        spanishButton.addEventListener('click', function() {
            setLanguage('es');
        });

        return true;
    }

    function init() {
        collectTextNodes();
        if (!textNodes.length) {
            return;
        }

        if (!findSwitcherElements()) {
            return;
        }

        updateLanguage();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
