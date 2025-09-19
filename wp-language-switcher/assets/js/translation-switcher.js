(function() {
    var originalDocumentLang = document.documentElement.getAttribute('lang') || 'en';

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

    function isIgnorableText(node) {
        return node && node.nodeType === Node.TEXT_NODE && !node.textContent.trim();
    }

    function mapTextNode(targetNode, sourceNode, textMappings, seenTextNodes) {
        if (seenTextNodes.has(targetNode)) {
            return;
        }

        seenTextNodes.add(targetNode);
        textMappings.push({
            node: targetNode,
            en: targetNode.textContent,
            es: sourceNode.textContent
        });
    }

    function mapAttributes(targetElement, sourceElement, attributeMappings, attributeLookup) {
        var sourceAttributes = Array.prototype.slice.call(sourceElement.attributes);
        if (sourceAttributes.length === 0) {
            return;
        }

        var stored = attributeLookup.get(targetElement);
        if (!stored) {
            stored = {};
            attributeLookup.set(targetElement, stored);
        }

        sourceAttributes.forEach(function(attr) {
            if (/^on/i.test(attr.name)) {
                return;
            }

            var currentValue = targetElement.getAttribute(attr.name);
            if (currentValue === attr.value) {
                return;
            }

            if (!stored[attr.name]) {
                stored[attr.name] = {
                    element: targetElement,
                    name: attr.name,
                    en: currentValue,
                    es: attr.value
                };
                attributeMappings.push(stored[attr.name]);
            } else {
                stored[attr.name].es = attr.value;
            }
        });
    }

    function buildMappings(targetParent, sourceParent, textMappings, attributeMappings, seenTextNodes, attributeLookup) {
        var targetChildren = Array.prototype.slice.call(targetParent.childNodes);
        var sourceChildren = Array.prototype.slice.call(sourceParent.childNodes);
        var targetIndex = 0;
        var sourceIndex = 0;

        while (targetIndex < targetChildren.length && sourceIndex < sourceChildren.length) {
            var targetChild = targetChildren[targetIndex];
            var sourceChild = sourceChildren[sourceIndex];

            if (targetChild.nodeType === Node.COMMENT_NODE) {
                targetIndex++;
                continue;
            }

            if (sourceChild.nodeType === Node.COMMENT_NODE) {
                sourceIndex++;
                continue;
            }

            if (isIgnorableText(targetChild) && isIgnorableText(sourceChild)) {
                targetIndex++;
                sourceIndex++;
                continue;
            }

            if (isIgnorableText(targetChild)) {
                targetIndex++;
                continue;
            }

            if (isIgnorableText(sourceChild)) {
                sourceIndex++;
                continue;
            }

            if (targetChild.nodeType === Node.TEXT_NODE && sourceChild.nodeType === Node.TEXT_NODE) {
                mapTextNode(targetChild, sourceChild, textMappings, seenTextNodes);
                targetIndex++;
                sourceIndex++;
                continue;
            }

            if (targetChild.nodeType === Node.ELEMENT_NODE && sourceChild.nodeType === Node.ELEMENT_NODE && targetChild.tagName === sourceChild.tagName) {
                if (targetChild.tagName === 'SCRIPT' || targetChild.tagName === 'STYLE') {
                    targetIndex++;
                    sourceIndex++;
                    continue;
                }

                mapAttributes(targetChild, sourceChild, attributeMappings, attributeLookup);
                buildMappings(targetChild, sourceChild, textMappings, attributeMappings, seenTextNodes, attributeLookup);
                targetIndex++;
                sourceIndex++;
                continue;
            }

            targetIndex++;
            sourceIndex++;
        }
    }

    function applyLanguage(textMappings, attributeMappings, lang) {
        var useSpanish = lang === 'es';

        textMappings.forEach(function(entry) {
            var value = useSpanish ? entry.es : entry.en;
            if (value !== undefined && entry.node.textContent !== value) {
                entry.node.textContent = value;
            }
        });

        attributeMappings.forEach(function(entry) {
            var value = useSpanish ? entry.es : entry.en;
            if (value === null || value === undefined) {
                entry.element.removeAttribute(entry.name);
                return;
            }

            if (entry.element.getAttribute(entry.name) !== value) {
                entry.element.setAttribute(entry.name, value);
            }
        });
    }

    function mountControls(root, controlsWrapper) {
        var navLinkedIn = document.querySelector('.nav-cta .nav-linkedin');
        if (navLinkedIn && navLinkedIn.parentNode) {
            controlsWrapper.classList.add('kls-switcher--nav');
            navLinkedIn.insertAdjacentElement('beforebegin', controlsWrapper);
            return true;
        }

        var navCta = document.querySelector('.nav-cta');
        if (navCta) {
            controlsWrapper.classList.add('kls-switcher--nav');
            navCta.appendChild(controlsWrapper);
            return true;
        }

        var linkedIn = document.querySelector('.nav-linkedin, a[href*="linkedin.com" i]');
        if (linkedIn) {
            controlsWrapper.classList.add('kls-switcher--nav');

            var navItem = linkedIn.closest('li');
            if (navItem && navItem.parentNode) {
                var item = document.createElement(navItem.tagName.toLowerCase());
                if (navItem.className) {
                    item.className = navItem.className;
                }
                item.classList.add('kls-switcher-nav-item');
                item.appendChild(controlsWrapper);
                navItem.parentNode.insertBefore(item, navItem);
                return true;
            }

            linkedIn.insertAdjacentElement('beforebegin', controlsWrapper);
            return true;
        }

        return false;
    }

    function initializePortal() {
        var root = document.getElementById('kls-switcher-root');
        if (!root || root.dataset.initialized) {
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

        var buttonGroup = document.createElement('div');
        buttonGroup.className = 'kls-switcher__buttons';
        buttonGroup.setAttribute('role', 'tablist');
        buttonGroup.setAttribute('aria-label', root.dataset.label || 'Language selector');

        var controlsWrapper = document.createElement('div');
        controlsWrapper.className = 'kls-switcher';
        controlsWrapper.appendChild(buttonGroup);

        var panelsWrapper = document.createElement('div');
        panelsWrapper.className = 'kls-switcher__panels';

        var contentPanelId = 'kls-lang-content';
        var contentPanel = document.createElement('div');
        contentPanel.className = 'kls-switcher__panel is-active';
        contentPanel.dataset.lang = 'content';
        contentPanel.id = contentPanelId;
        contentPanel.setAttribute('role', 'document');
        contentPanel.appendChild(moveNodesIntoFragment(siblings));
        panelsWrapper.appendChild(contentPanel);

        var englishButton = createButton(root.dataset.englishLabel || 'English', 'en', contentPanelId);
        var spanishButton = createButton(root.dataset.spanishLabel || 'Español', 'es', contentPanelId);
        buttonGroup.appendChild(englishButton);
        buttonGroup.appendChild(spanishButton);

        var textMappings = [];
        var attributeMappings = [];
        var seenTextNodes = new WeakSet();
        var attributeLookup = new WeakMap();

        var translationContainer = document.createElement('div');
        translationContainer.innerHTML = template.innerHTML;
        buildMappings(contentPanel, translationContainer, textMappings, attributeMappings, seenTextNodes, attributeLookup);
        template.remove();

        function activateLanguage(lang) {
            root.dataset.activeLang = lang;
            document.documentElement.setAttribute('data-kls-lang', lang);
            document.documentElement.setAttribute('lang', lang === 'es' ? 'es' : originalDocumentLang);

            var buttons = buttonGroup.querySelectorAll('.kls-switcher__button');
            buttons.forEach(function(button) {
                var isActive = button.dataset.lang === lang;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                button.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            if (lang === 'es' || lang === 'en') {
                applyLanguage(textMappings, attributeMappings, lang);
            }
        }

        function finalizeMount() {
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

                activateLanguage(lang);
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
                    activateLanguage(nextButton.dataset.lang);
                }
            });

            root.dataset.initialized = '1';

            var defaultLang = root.dataset.default || 'en';
            if (defaultLang !== 'en' && defaultLang !== 'es') {
                defaultLang = 'en';
            }

            activateLanguage(defaultLang);
        }

        var attempts = 0;

        (function attemptMount() {
            if (mountControls(root, controlsWrapper)) {
                finalizeMount();
                return;
            }

            if (attempts < 10) {
                attempts++;
                window.setTimeout(attemptMount, 150);
                return;
            }

            controlsWrapper.classList.add('kls-switcher--fallback');
            root.appendChild(controlsWrapper);
            finalizeMount();
        })();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePortal);
    } else {
        initializePortal();
    }
})();
