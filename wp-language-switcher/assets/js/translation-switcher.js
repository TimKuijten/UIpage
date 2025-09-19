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
    var switcherIndicator;
    var navigationWrapper = null;
    var navigationInterval = null;
    var navigationObserver = null;

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
            if (switcherIndicator) {
                switcherIndicator.style.transform = 'translateX(100%)';
            }
        } else {
            spanishButton.classList.remove(activeClass);
            englishButton.classList.add(activeClass);
            spanishButton.setAttribute('aria-pressed', 'false');
            englishButton.setAttribute('aria-pressed', 'true');
            if (switcherIndicator) {
                switcherIndicator.style.transform = 'translateX(0)';
            }
        }
    }

    function createSwitcher() {
        switcherContainer = document.createElement('div');
        switcherContainer.className = 'kls-switcher';
        switcherContainer.setAttribute('role', 'group');
        switcherContainer.setAttribute('aria-label', 'Language selector');

        switcherIndicator = document.createElement('span');
        switcherIndicator.className = 'kls-switcher__indicator';
        switcherContainer.appendChild(switcherIndicator);

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

        ensureNavigationInjection();
        updateButtonStates();
    }

    function ensureNavigationInjection() {
        if (!switcherContainer) {
            return;
        }

        if (injectIntoNavigation()) {
            return;
        }

        if (!navigationObserver) {
            try {
                navigationObserver = new MutationObserver(function() {
                    if (injectIntoNavigation()) {
                        stopNavigationWatchers();
                    }
                });

                navigationObserver.observe(document.body, { childList: true, subtree: true });
            } catch (error) {
                navigationObserver = null;
            }
        }

        if (!navigationInterval) {
            var attempts = 0;
            navigationInterval = window.setInterval(function() {
                attempts += 1;
                if (injectIntoNavigation() || attempts >= 40) {
                    stopNavigationWatchers();
                }
            }, 250);
        }
    }

    function stopNavigationWatchers() {
        if (navigationObserver) {
            navigationObserver.disconnect();
            navigationObserver = null;
        }

        if (navigationInterval) {
            window.clearInterval(navigationInterval);
            navigationInterval = null;
        }
    }

    function ensureNavigationWrapper(tagName, additionalClasses) {
        var normalizedTag = (tagName || '').toLowerCase() || 'div';

        if (navigationWrapper && navigationWrapper.tagName.toLowerCase() !== normalizedTag) {
            if (navigationWrapper.parentNode) {
                navigationWrapper.parentNode.removeChild(navigationWrapper);
            }
            navigationWrapper = null;
        }

        if (!navigationWrapper) {
            navigationWrapper = document.createElement(normalizedTag);
            navigationWrapper.className = 'kls-switcher__item';
        }

        if (additionalClasses) {
            additionalClasses.split(/\s+/).forEach(function(className) {
                if (!className) {
                    return;
                }

                if (!navigationWrapper.classList.contains(className)) {
                    navigationWrapper.classList.add(className);
                }
            });
        }

        if (!navigationWrapper.classList.contains('kls-switcher__item')) {
            navigationWrapper.classList.add('kls-switcher__item');
        }

        return navigationWrapper;
    }

    function injectIntoNavigation() {
        if (!switcherContainer) {
            return false;
        }

        var linkedInLink = document.querySelector('a[href*="linkedin.com" i]');
        var referenceItem = linkedInLink ? linkedInLink.closest('li, .menu-item, .wp-block-navigation-item') : null;
        var referenceParent = referenceItem && referenceItem.parentNode ? referenceItem.parentNode : null;

        if (!referenceParent && linkedInLink && linkedInLink.parentNode) {
            referenceParent = linkedInLink.parentNode;
        }

        if (referenceParent && document.body.contains(referenceParent)) {
            var wrapperTag;
            var wrapperClasses = '';

            if (referenceItem && referenceItem.nodeName) {
                wrapperTag = referenceItem.nodeName;
                wrapperClasses = referenceItem.className || '';
            } else if (referenceParent.nodeName) {
                wrapperTag = referenceParent.nodeName;
                wrapperClasses = referenceParent.className || '';
            }

            var wrapper = ensureNavigationWrapper(wrapperTag, wrapperClasses);

            if (referenceItem && referenceItem.parentNode === referenceParent) {
                if (wrapper.parentNode !== referenceParent || wrapper.nextSibling !== referenceItem) {
                    referenceParent.insertBefore(wrapper, referenceItem);
                }
            } else if (referenceParent.firstChild) {
                if (wrapper.parentNode !== referenceParent || wrapper !== referenceParent.firstChild) {
                    referenceParent.insertBefore(wrapper, referenceParent.firstChild);
                }
            } else if (wrapper.parentNode !== referenceParent) {
                referenceParent.appendChild(wrapper);
            }

            if (switcherContainer.parentNode !== wrapper) {
                wrapper.appendChild(switcherContainer);
            }

            switcherContainer.classList.add('kls-switcher--nav');
            return true;
        }

        var navigation = document.querySelector('#primary-menu, nav .primary-menu, nav[aria-label*="primary" i] ul, nav[aria-label*="primary" i] .menu, nav[aria-label*="navigation" i] ul, .primary-menu, .main-header-menu, .menu');

        if (navigation && document.body.contains(navigation)) {
            var fallbackWrapper;
            if (navigation.tagName && navigation.tagName.toLowerCase() === 'ul') {
                fallbackWrapper = ensureNavigationWrapper('li', 'menu-item');
                if (fallbackWrapper.parentNode !== navigation) {
                    navigation.appendChild(fallbackWrapper);
                }
            } else {
                fallbackWrapper = ensureNavigationWrapper(navigation.nodeName, navigation.className || '');
                if (fallbackWrapper.parentNode !== navigation) {
                    navigation.appendChild(fallbackWrapper);
                }
            }

            if (switcherContainer.parentNode !== fallbackWrapper) {
                fallbackWrapper.appendChild(switcherContainer);
            }

            switcherContainer.classList.add('kls-switcher--nav');
            return true;
        }

        if (switcherContainer) {
            switcherContainer.classList.remove('kls-switcher--nav');
        }

        return false;
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
