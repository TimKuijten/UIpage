(function(){
  if (typeof window === 'undefined') {
    return;
  }

  const data = window.UILangSwitcherData || {};
  const pages = data.pages || {};
  const storageKey = data.storageKey || 'ui_language_preference';
  const queryParam = data.queryParam || 'lang';

  const readStoredPreference = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      return raw ? JSON.parse(raw) : {};
    } catch (err) {
      return {};
    }
  };

  const writeStoredPreference = (prefs) => {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(prefs));
    } catch (err) {
      /* noop */
    }
  };

  const getQueryLang = () => {
    try {
      const params = new URLSearchParams(window.location.search);
      const lang = (params.get(queryParam) || '').toLowerCase();
      if (lang === 'en' || lang === 'es') {
        return lang;
      }
    } catch (err) {
      /* noop */
    }
    return '';
  };

  const resizeFrame = (frame) => {
    if (!frame || !frame.contentWindow || !frame.contentDocument) {
      return;
    }
    try {
      const doc = frame.contentDocument;
      const height = Math.max(
        doc.body ? doc.body.scrollHeight : 0,
        doc.documentElement ? doc.documentElement.scrollHeight : 0
      );
      if (height) {
        frame.style.height = (height + 40) + 'px';
      }
    } catch (err) {
      /* noop */
    }
  };

  const queryLang = getQueryLang();
  const storedPrefs = readStoredPreference();

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.ui-lang-switcher').forEach(function(container){
      const slug = container.dataset.page;
      if (!slug || !pages[slug]) {
        return;
      }
      const iframe = container.querySelector('iframe.ui-lang-switcher__iframe');
      const buttons = Array.from(container.querySelectorAll('[data-lang]'));
      if (!iframe || !buttons.length) {
        return;
      }

      const available = pages[slug];
      const baseTitle = iframe.dataset.titleBase || iframe.getAttribute('title') || '';
      const applyTitle = (lang) => {
        if (!baseTitle) {
          return;
        }
        iframe.setAttribute('title', baseTitle + ' (' + lang.toUpperCase() + ')');
      };
      const syncButtons = (lang) => {
        buttons.forEach(function(btn){
          const isActive = btn.dataset.lang === lang;
          btn.classList.toggle('is-active', isActive);
          btn.setAttribute('aria-pressed', String(isActive));
        });
      };
      let current = queryLang && available[queryLang] ? queryLang : '';
      if (!current) {
        const stored = storedPrefs && storedPrefs[slug];
        if (stored && available[stored]) {
          current = stored;
        }
      }
      if (!current) {
        current = container.dataset.defaultLang === 'es' ? 'es' : 'en';
      }
      if (!available[current]) {
        current = 'en';
      }

      const setLanguage = (lang) => {
        if (!available[lang] || lang === current) {
          return;
        }
        current = lang;
        applyTitle(lang);
        iframe.src = available[lang];
        syncButtons(lang);
        storedPrefs[slug] = lang;
        writeStoredPreference(storedPrefs);
      };

      iframe.addEventListener('load', function(){
        resizeFrame(iframe);
        setTimeout(function(){ resizeFrame(iframe); }, 200);
        syncButtons(current);
      });
      window.addEventListener('resize', function(){
        resizeFrame(iframe);
      });

      buttons.forEach(function(btn){
        btn.addEventListener('click', function(){
          setLanguage(btn.dataset.lang);
          iframe.focus();
        });
      });

      // initial load
      applyTitle(current);
      syncButtons(current);
      storedPrefs[slug] = current;
      writeStoredPreference(storedPrefs);
      iframe.src = available[current];
    });
  });
})();
