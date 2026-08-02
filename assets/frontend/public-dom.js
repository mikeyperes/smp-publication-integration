(function (window, document) {
    'use strict';

    if (window.SmpiPublicDomRuntime) return;

    var articleSelectors = [
        '.elementor-widget-theme-post-content .elementor-widget-container',
        '.elementor-widget-theme-post-content',
        '.elementor-widget-post-content .elementor-widget-container',
        '.elementor-widget-post-content',
        'article .entry-content',
        '.entry-content',
        '.post-content'
    ];

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    }

    function bodyHas(className) {
        return !!document.body && document.body.classList.contains(className);
    }

    function query(scope, selector) {
        if (!scope) return null;
        if (scope.nodeType === 1 && scope.matches && scope.matches(selector)) return scope;
        return scope.querySelector ? scope.querySelector(selector) : null;
    }

    function visible(element) {
        if (!element || !element.getBoundingClientRect) return false;
        var rect = element.getBoundingClientRect();
        return rect.width > 1 && rect.height > 1 && window.getComputedStyle(element).display !== 'none';
    }

    function breadcrumbSelectors(bar) {
        try {
            var selectors = JSON.parse(bar.getAttribute('data-smpi-header-selectors') || '[]');
            return Array.isArray(selectors) ? selectors.filter(function (selector) {
                return typeof selector === 'string' && selector.length > 0 && selector.length <= 240;
            }) : [];
        } catch (error) {
            return [];
        }
    }

    function removeInjectedBreadcrumbs(except) {
        document.querySelectorAll('[data-smp-ajax-companion-rendered="smpi-breadcrumbs"],[data-smpi-breadcrumbs-injected]').forEach(function (bar) {
            if (bar !== except) bar.remove();
        });
    }

    function initializeBreadcrumbs() {
        var source = document.querySelector('template[data-smp-ajax-companion="smpi-breadcrumbs"]');
        if (!bodyHas('smpi-runtime-breadcrumbs')) {
            removeInjectedBreadcrumbs(null);
            return;
        }

        if (!source || !source.content || !source.content.firstElementChild) {
            removeInjectedBreadcrumbs(null);
            return;
        }

        var bar = source.content.firstElementChild.cloneNode(true);

        var target = null;
        breadcrumbSelectors(bar).some(function (selector) {
            try {
                var candidate = document.querySelector(selector);
                if (candidate && visible(candidate)) {
                    target = candidate;
                    return true;
                }
            } catch (error) {
                return false;
            }
            return false;
        });

        removeInjectedBreadcrumbs(bar);
        bar.hidden = false;
        bar.setAttribute('data-smp-ajax-companion-rendered', 'smpi-breadcrumbs');
        bar.setAttribute('data-smpi-breadcrumbs-injected', '1');
        if (target && target.parentNode) target.insertAdjacentElement('afterend', bar);
        else if (document.body.firstElementChild) document.body.insertBefore(bar, document.body.firstElementChild);
        else document.body.appendChild(bar);
    }

    function articleRoot(scope) {
        for (var index = 0; index < articleSelectors.length; index += 1) {
            var root = query(scope, articleSelectors[index]);
            if (root) return root;
        }
        return null;
    }

    function owned(element) {
        return !element.closest('.smpi-post-summary,.smpi-post-faqs,.smpi-table-of-contents,.smpi-breadcrumbs');
    }

    function contactParagraph(element) {
        var text = (element.textContent || '').replace(/\s+/g, ' ').trim();
        var previous = element.previousElementSibling;
        var followsContactHeading = previous
            && /^H[2-6]$/.test(previous.tagName)
            && /^(contact|contact information|media contact|press contact|for media inquiries)$/i.test((previous.textContent || '').replace(/\s+/g, ' ').trim());

        return !!element.closest('address,.smpi-contact-information,.contact-information,.contact-info,.media-contact,.press-contact')
            || followsContactHeading
            || (text.length < 240 && !!element.querySelector('a[href^="tel:"],a[href^="mailto:"]'));
    }

    function leadParagraph(element) {
        return owned(element)
            && visible(element)
            && !!(element.textContent || '').trim()
            && !element.closest('aside,blockquote,figure,nav,footer,address')
            && !contactParagraph(element);
    }

    function normalizeArticleLead(root) {
        var lead = Array.prototype.find.call(root.querySelectorAll('p'), leadParagraph) || null;
        root.querySelectorAll('.smpi-article-lead').forEach(function (element) {
            if (element !== lead) element.classList.remove('smpi-article-lead');
        });
        if (lead) lead.classList.add('smpi-article-lead');
    }

    function numberedListStyle() {
        var prefix = 'smpi-runtime-numbered-list-';
        var classes = document.body ? Array.prototype.slice.call(document.body.classList) : [];
        var match = classes.find(function (className) { return className.indexOf(prefix) === 0; });
        return match ? match.slice(prefix.length) : 'none';
    }

    function initializeArticle(scope) {
        if (!bodyHas('smpi-runtime-article-markup')) return;
        var root = articleRoot(scope);
        if (!root) return;

        root.classList.add('smpi-template', 'smpi-template--article-content', 'smpi-template-content', 'smpi-article-content');
        root.querySelectorAll('a').forEach(function (element) {
            if (owned(element)) element.classList.add('smpi-template-link', 'smpi-article-link');
        });
        root.querySelectorAll('ol,ul').forEach(function (element) {
            if (owned(element)) element.classList.add('smpi-template-list', 'smpi-article-list');
        });
        root.querySelectorAll('li').forEach(function (element) {
            if (owned(element)) element.classList.add('smpi-template-item', 'smpi-article-list-item');
        });
        root.querySelectorAll('p').forEach(function (element) {
            if (owned(element)) element.classList.add('smpi-template-text', 'smpi-article-paragraph');
        });
        root.querySelectorAll('img').forEach(function (element) {
            if (owned(element)) element.classList.add('smpi-template-image', 'smpi-article-image');
        });

        if (bodyHas('smpi-runtime-article-headings')) {
            root.querySelectorAll('h2,h3,h4').forEach(function (element) {
                if (owned(element)) element.classList.add('smpi-template-title', 'smpi-article-heading', 'smpi-article-heading--' + element.tagName.toLowerCase());
            });
        }

        var listStyle = numberedListStyle();
        if (bodyHas('smpi-runtime-article-numbered-lists') && listStyle !== 'none') {
            root.querySelectorAll('ol').forEach(function (list) {
                if (!owned(list)
                    || (list.parentElement && list.parentElement.closest('ol'))
                    || list.matches('.smpi-post-summary-list,.smpi-post-faq-list,.smpi-toc-list,.wp-block-footnotes')) return;
                list.classList.add('smpi-template', 'smpi-template--article-numbered-list', 'smpi-template-list', 'smpi-article-list', 'smpi-numbered-list', 'smpi-numbered-list--' + listStyle);
                Array.prototype.slice.call(list.children).forEach(function (item) {
                    if (item.tagName !== 'LI') return;
                    item.classList.add('smpi-template-item', 'smpi-article-list-item', 'smpi-numbered-list-item');
                    item.querySelectorAll(':scope > h3,:scope > h4,:scope > h5,:scope > strong').forEach(function (title) {
                        title.classList.add('smpi-template-title', 'smpi-numbered-list-title');
                    });
                    item.querySelectorAll(':scope > p').forEach(function (text) {
                        text.classList.add('smpi-template-text', 'smpi-numbered-list-text');
                    });
                    item.querySelectorAll('a').forEach(function (link) {
                        link.classList.add('smpi-template-link', 'smpi-numbered-list-link');
                    });
                });
            });
        }

        if (bodyHas('smpi-runtime-article-dropcap')) normalizeArticleLead(root);
        else root.querySelectorAll('.smpi-article-lead').forEach(function (element) {
            element.classList.remove('smpi-article-lead');
        });
    }

    function initialize(scope, navigation) {
        var root = scope && scope.querySelector ? scope : document;
        initializeBreadcrumbs();
        initializeArticle(root);
    }

    document.addEventListener('smp:content-ready', function (event) {
        initialize(event.detail && event.detail.root ? event.detail.root : document, true);
    });

    window.SmpiPublicDomRuntime = { init: initialize };
    ready(function () { initialize(document, false); });
})(window, document);
