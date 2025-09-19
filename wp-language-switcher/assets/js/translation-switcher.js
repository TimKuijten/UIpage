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
    var initialized = false;

    function findLogoElement() {
        var selectors = [
            '.nav .logo',
            '.site-header .logo',
            '.wp-block-site-logo',
            '.site-branding .custom-logo-link',
            '.site-branding',
            '.custom-logo-link',
            '.site-logo a',
            '.site-logo'
        ];

        for (var i = 0; i < selectors.length; i += 1) {
            var element = document.querySelector(selectors[i]);
            if (!element) {
                continue;
            }

            if (element.tagName && element.tagName.toLowerCase() === 'img' && element.parentElement) {
                element = element.parentElement;
            }

            if (element.classList && element.classList.contains('custom-logo-link') && element.parentElement && element.parentElement.classList && element.parentElement.classList.contains('wp-block-site-logo')) {
                element = element.parentElement;
            }

            return element;
        }

        return null;
    }

    function removeEmptyWrapper(originalParent, portal) {
        if (!originalParent || originalParent === portal) {
            return;
        }

        if (!originalParent.classList || !originalParent.classList.contains('kls-switcher__item')) {
            return;
        }

        var child = originalParent.firstChild;
        while (child) {
            if (child.nodeType === 1) {
                return;
            }

            if (child.nodeType === 3 && child.nodeValue && child.nodeValue.trim()) {
                return;
            }

            child = child.nextSibling;
        }

        originalParent.remove();
    }

    function mountSwitcherByLinkedIn(switcher, portal) {
        var linkedinLink = document.querySelector('a[href*="linkedin.com"]');
        if (!linkedinLink) {
            return false;
        }

        var originalParent = switcher.parentNode;
        var navItem = linkedinLink.closest('li, .wp-block-navigation-item, .menu-item');
        if (navItem && navItem.parentNode) {
            var parentList = navItem.parentNode;
            var tagName = navItem.tagName ? navItem.tagName.toLowerCase() : 'div';
            var wrapper = document.createElement(tagName === 'li' ? 'li' : 'div');

            if (navItem.classList && navItem.classList.length) {
                navItem.classList.forEach(function(cls) {
                    wrapper.classList.add(cls);
                });
            }

            wrapper.classList.add('kls-switcher__item');
            switcher.classList.add('kls-switcher--nav');
            wrapper.appendChild(switcher);
            parentList.insertBefore(wrapper, navItem);

            if (portal) {
                portal.hidden = true;
            }

            removeEmptyWrapper(originalParent, portal);

            return true;
        }

        var parent = linkedinLink.parentNode;
        if (!parent) {
            return false;
        }

        switcher.classList.add('kls-switcher--nav');
        if (parent.classList) {
            parent.classList.add('kls-switcher__item');
        }

        parent.insertBefore(switcher, linkedinLink);

        if (portal) {
            portal.hidden = true;
        }

        removeEmptyWrapper(originalParent, portal);

        return true;
    }

    function mountSwitcherNearLogo(switcher, portal) {
        if (document.querySelector('.kls-switcher--logo')) {
            return true;
        }

        if (!switcher) {
            return false;
        }

        var logoElement = findLogoElement();
        if (logoElement && logoElement.parentNode) {
            var parent = logoElement.parentNode;
            var wrapper = document.createElement('div');
            wrapper.classList.add('kls-switcher__item', 'kls-switcher__item--logo');

            var originalParent = switcher.parentNode;
            switcher.classList.add('kls-switcher--nav', 'kls-switcher--logo');
            wrapper.appendChild(switcher);

            parent.insertBefore(wrapper, logoElement);

            if (portal) {
                portal.hidden = true;
            }

            removeEmptyWrapper(originalParent, portal);

            return true;
        }

        return false;
    }

    function mountPreferredSwitcherLocation(allowFallback) {
        var portal = document.getElementById('kls-switcher-root');
        var switcher = portal ? portal.querySelector('.kls-switcher') : null;

        if (!switcher) {
            switcher = document.querySelector('.kls-switcher');
        }

        if (!switcher) {
            return false;
        }

        if (mountSwitcherByLinkedIn(switcher, portal)) {
            return true;
        }

        if (allowFallback && mountSwitcherNearLogo(switcher, portal)) {
            return true;
        }

        return false;
    }

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

    function findSwitcherElements(allowFallback) {
        if (!mountPreferredSwitcherLocation(allowFallback) && allowFallback) {
            var portal = document.getElementById('kls-switcher-root');
            if (portal) {
                portal.removeAttribute('hidden');
            }
        }

        switcherContainer = document.querySelector('.kls-switcher--logo, .kls-switcher--nav');

        if (!switcherContainer && allowFallback) {
            var fallbackPortal = document.getElementById('kls-switcher-root');
            if (fallbackPortal) {
                var fallbackSwitcher = fallbackPortal.querySelector('.kls-switcher');
                if (fallbackSwitcher) {
                    switcherContainer = fallbackSwitcher;
                }
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

        if (!englishButton.dataset.klsBound) {
            englishButton.addEventListener('click', function() {
                setLanguage('en');
            });
            englishButton.dataset.klsBound = 'true';
        }

        if (!spanishButton.dataset.klsBound) {
            spanishButton.addEventListener('click', function() {
                setLanguage('es');
            });
            spanishButton.dataset.klsBound = 'true';
        }

        return true;
    }

    function init() {
        collectTextNodes();
        if (!textNodes.length) {
            return;
        }

        var attempts = 0;
        var fallbackThreshold = 10;
        var maxAttempts = 150;
        var attemptDelay = 200;

        function attemptMount() {
            var allowFallback = attempts >= fallbackThreshold;
            var found = findSwitcherElements(allowFallback);

            if (found && !initialized) {
                initialized = true;
                updateLanguage();
            }

            if (document.querySelector('.kls-switcher--logo, .kls-switcher--nav')) {
                return;
            }

            if (attempts < maxAttempts) {
                attempts += 1;
                window.setTimeout(attemptMount, attemptDelay);
            }
        }

        attemptMount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
