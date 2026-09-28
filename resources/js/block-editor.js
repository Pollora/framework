/**
 * Pollora — the editor side of blocks rendered on the server.
 *
 * A block whose block.json has a "render" template (render.blade.php) is
 * edited through window.pollora.blocks:
 *
 *     registerBlockType(metadata.name, {
 *         edit: window.pollora.blocks.bladeEdit(metadata),
 *         save: window.pollora.blocks.save,
 *     });
 *
 * The edit component asks the server for the template's HTML (the core
 * block-renderer REST route, which renders it as a preview), turns that HTML
 * into elements, and puts the editable inner blocks where the template wrote
 * <InnerBlocks />. The server leaves the tag in a preview and replaces it
 * with the inner blocks on the page, inside the same wrapper div.
 *
 * Plain JavaScript on the wp.* globals: the framework ships it without a
 * build, as the inline script of the "pollora-block-editor" handle.
 */
(function (wp, root) {
    'use strict';

    var el = wp.element.createElement;
    var useEffect = wp.element.useEffect;
    var useMemo = wp.element.useMemo;
    var useState = wp.element.useState;
    var blockEditor = wp.blockEditor;
    var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

    /** Class of the inner blocks' wrapper when the tag names none (as on the server). */
    var DEFAULT_CLASS = 'pollora-inner-blocks';

    /** Wait after an attribute change before asking for a new preview. */
    var REFRESH_DELAY = 200;

    /**
     * The tag, self-closing or not. The browser's HTML parser does not know
     * self-closing custom tags: <InnerBlocks /> would swallow what follows it,
     * so the first tag is rewritten to an element with an explicit end.
     */
    var TAG = /<InnerBlocks\b([^>]*?)\s*(?:\/>|>\s*<\/InnerBlocks\s*>)/gi;
    var SLOT = 'pollora-inner-blocks-slot';

    /** Attribute names the HTML parser lowercased, back to their React props. */
    var PROP_NAMES = {
        'class': 'className',
        classname: 'className',
        'for': 'htmlFor',
        allowedblocks: 'allowedBlocks',
        defaultblock: 'defaultBlock',
        directinsert: 'directInsert',
        prioritizedinserterblocks: 'prioritizedInserterBlocks',
        templatelock: 'templateLock',
        templateinsertupdatesselection: 'templateInsertUpdatesSelection',
        accesskey: 'accessKey',
        autocomplete: 'autoComplete',
        autofocus: 'autoFocus',
        colspan: 'colSpan',
        contenteditable: 'contentEditable',
        crossorigin: 'crossOrigin',
        datetime: 'dateTime',
        enctype: 'encType',
        frameborder: 'frameBorder',
        maxlength: 'maxLength',
        minlength: 'minLength',
        novalidate: 'noValidate',
        readonly: 'readOnly',
        referrerpolicy: 'referrerPolicy',
        rowspan: 'rowSpan',
        srcset: 'srcSet',
        tabindex: 'tabIndex',
        usemap: 'useMap'
    };

    /** HTML attributes whose mere presence means true. */
    var BOOLEAN_PROPS = ['allowFullScreen', 'autoFocus', 'checked', 'controls', 'default', 'defer', 'disabled', 'hidden', 'loop', 'multiple', 'muted', 'noValidate', 'open', 'playsInline', 'readOnly', 'required', 'reversed', 'selected'];

    /** Elements that never have children. */
    var VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    function camelCase(name) {
        return name.replace(/-([a-z])/g, function (match, letter) {
            return letter.toUpperCase();
        });
    }

    function propName(name) {
        if (PROP_NAMES[name]) {
            return PROP_NAMES[name];
        }

        // data-* and aria-* keep their names; SVG's stroke-width and the like
        // become the camelCase React expects.
        return /^(data|aria)-/.test(name) ? name : camelCase(name);
    }

    function styleObject(css) {
        var style = {};

        css.split(';').forEach(function (declaration) {
            var colon = declaration.indexOf(':');

            if (colon < 1) {
                return;
            }

            var property = declaration.slice(0, colon).trim();
            var value = declaration.slice(colon + 1).trim();

            // Custom properties (--x) keep their names.
            style[property.indexOf('--') === 0 ? property : camelCase(property)] = value;
        });

        return style;
    }

    /** A value on the <InnerBlocks> tag: JSON for arrays and objects, booleans for "true"/"false". */
    function slotValue(value) {
        var trimmed = value.trim();

        if (trimmed === 'true' || trimmed === 'false') {
            return trimmed === 'true';
        }

        if (trimmed.charAt(0) === '[' || trimmed.charAt(0) === '{') {
            try {
                return JSON.parse(trimmed);
            } catch (error) {
                // eslint-disable-next-line no-console
                console.warn('Pollora: <InnerBlocks> attribute is not valid JSON', value);
            }
        }

        return value;
    }

    function props(element, isSlot, key) {
        var result = { key: key };

        Array.prototype.forEach.call(element.attributes, function (attribute) {
            var name = attribute.name;

            // Event handlers written as strings cannot run as React props.
            if (name.indexOf('on') === 0) {
                return;
            }

            var prop = propName(name);

            if (prop === 'style') {
                result.style = styleObject(attribute.value);
            } else if (isSlot) {
                result[prop] = slotValue(attribute.value);
            } else if (attribute.value === '' && BOOLEAN_PROPS.indexOf(prop) !== -1) {
                result[prop] = true;
            } else {
                result[prop] = attribute.value;
            }
        });

        return result;
    }

    /** Where the template wrote <InnerBlocks />: the editable inner blocks, in the page's wrapper. */
    function InnerBlocksSlot(slotProps) {
        var className = slotProps.className || DEFAULT_CLASS;
        var options = {};

        Object.keys(slotProps).forEach(function (name) {
            if (name !== 'className' && name !== 'style') {
                options[name] = slotProps[name];
            }
        });

        return el('div', useInnerBlocksProps({ className: className, style: slotProps.style }, options));
    }

    function toElement(node, key) {
        if (node.nodeType === 3) {
            return node.nodeValue;
        }

        if (node.nodeType !== 1) {
            return null;
        }

        var tag = node.nodeName.toLowerCase();

        // A script in a preview would run in the editor: left out.
        if (tag === 'script') {
            return null;
        }

        if (tag === SLOT) {
            return el(InnerBlocksSlot, props(node, true, key));
        }

        var elementProps = props(node, false, key);

        if (tag === 'style') {
            elementProps.dangerouslySetInnerHTML = { __html: node.textContent };

            return el(tag, elementProps);
        }

        if (VOID_ELEMENTS.indexOf(tag) !== -1) {
            return el(tag, elementProps);
        }

        return el.apply(null, [tag, elementProps].concat(toElements(node.childNodes)));
    }

    function toElements(nodes) {
        return Array.prototype.map.call(nodes, function (node, index) {
            return toElement(node, index);
        });
    }

    /**
     * Elements for a preview's HTML, the first <InnerBlocks /> as the editable
     * inner blocks. Gutenberg keeps one list of inner blocks per block: any
     * further tag is dropped, as on the page.
     */
    function parse(html) {
        var isFirst = true;
        var marked = html.replace(TAG, function (match, attributes) {
            if (!isFirst) {
                // eslint-disable-next-line no-console
                console.warn('Pollora: a block template holds more than one <InnerBlocks />; only the first is kept.');

                return '';
            }

            isFirst = false;

            return '<' + SLOT + (attributes || '') + '></' + SLOT + '>';
        });
        var body = new window.DOMParser().parseFromString('<!DOCTYPE html><body>' + marked + '</body>', 'text/html').body;

        return toElements(body.childNodes);
    }

    /** The post being edited, when there is one: the preview renders in its context. */
    function currentPostId(select) {
        var editor = select('core/editor');

        return editor && editor.getCurrentPostId ? editor.getCurrentPostId() : null;
    }

    function bladeEdit(metadata) {
        function Edit(editProps) {
            var blockProps = blockEditor.useBlockProps();
            var state = useState(null);
            var html = state[0];
            var setHtml = state[1];
            var errorState = useState(null);
            var error = errorState[0];
            var setError = errorState[1];
            var serialized = JSON.stringify(editProps.attributes);
            var postId = wp.data.useSelect(currentPostId, []);

            useEffect(function () {
                var controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
                var timer = window.setTimeout(function () {
                    var data = { attributes: editProps.attributes, context: 'edit' };

                    if (postId) {
                        data.post_id = postId;
                    }

                    wp.apiFetch({
                        path: '/wp/v2/block-renderer/' + metadata.name,
                        method: 'POST',
                        data: data,
                        signal: controller ? controller.signal : undefined
                    }).then(function (response) {
                        setError(null);
                        setHtml(response && typeof response.rendered === 'string' ? response.rendered : '');
                    }).catch(function (reason) {
                        if (!reason || reason.name !== 'AbortError') {
                            setError(reason && reason.message ? reason.message : 'Error');
                        }
                    });
                }, html === null ? 0 : REFRESH_DELAY);

                return function () {
                    window.clearTimeout(timer);

                    if (controller) {
                        controller.abort();
                    }
                };
            }, [serialized, postId]);

            var elements = useMemo(function () {
                return html === null ? null : parse(html);
            }, [html]);

            if (elements === null) {
                return el('div', blockProps, error
                    ? el(wp.components.Notice, { status: 'error', isDismissible: false }, error)
                    : el(wp.components.Spinner));
            }

            return el.apply(null, ['div', blockProps].concat(elements));
        }

        Edit.displayName = 'PolloraBladeEdit(' + metadata.name + ')';

        return Edit;
    }

    /**
     * Store the inner blocks in the post: the page renders the block from its
     * template, with the inner blocks in place of <InnerBlocks />. A block
     * without inner blocks saves nothing, as with save: () => null.
     */
    function save() {
        return el(blockEditor.InnerBlocks.Content);
    }

    root.pollora = root.pollora || {};
    root.pollora.blocks = {
        bladeEdit: bladeEdit,
        save: save,
        parse: parse
    };
}(window.wp, window));
