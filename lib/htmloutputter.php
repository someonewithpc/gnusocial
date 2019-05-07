<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Low-level generator for HTML
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Output
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) {
    exit(1);
}

// Can include XHTML options but these are too fragile in practice.
define('PAGE_TYPE_PREFS', 'text/html');

/**
 * Low-level generator for HTML
 *
 * Abstracts some of the code necessary for HTML generation. Especially
 * has methods for generating HTML form elements. Note that these have
 * been created kind of haphazardly, not with an eye to making a general
 * HTML-creation class.
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Action
 * @see      XMLOutputter
 */
class HTMLOutputter extends XMLOutputter
{
    protected $DTD = ['doctype' => 'html',
        'spec' => '-//W3C//DTD XHTML 1.0 Strict//EN',
        'uri' => 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'];

    /**
     * Constructor
     *
     * Just wraps the XMLOutputter constructor.
     *
     * @param string $output URI to output to, default = stdout
     * @param boolean $indent Whether to indent output, default true
     */

    public function __construct($output = 'php://output', $indent = null)
    {
        parent::__construct($output, $indent);
    }

    /**
     * Start an HTML document
     *
     * If $type isn't specified, will attempt to do content negotiation.
     *
     * Attempts to do content negotiation for language, also.
     *
     * @param string $type MIME type to use; default is to do negotation.
     *
     * @return void
     * @throws ClientException
     * @todo extract content negotiation code to an HTTP module or class.
     *
     */

    public function startHTML($type = null)
    {
        if (!$type) {
            $httpaccept = isset($_SERVER['HTTP_ACCEPT']) ?
                $_SERVER['HTTP_ACCEPT'] : null;

            // XXX: allow content negotiation for RDF, RSS, or XRDS

            $cp = common_accept_to_prefs($httpaccept);
            $sp = common_accept_to_prefs(PAGE_TYPE_PREFS);

            $type = common_negotiate_type($cp, $sp);

            if (!$type) {
                // TRANS: Client exception 406
                throw new ClientException(_('This page is not available in a ' .
                    'media type you accept'), 406);
            }
        }

        header('Content-Type: ' . $type);

        // Output anti-framing headers to prevent clickjacking (respected by newer
        // browsers).
        if (common_config('javascript', 'bustframes')) {
            header('X-XSS-Protection: 1; mode=block'); // detect XSS Reflection attacks
            header('X-Frame-Options: SAMEORIGIN'); // no rendering if origin mismatch
        }

        $this->extraHeaders();
        if (preg_match("/.*\/.*xml/", $type)) {
            // Required for XML documents
            $this->startXML();
        }

        $this->writeDTD();

        $language = $this->getLanguage();

        $attrs = [
            'xmlns' => 'http://www.w3.org/1999/xhtml',
            'xml:lang' => $language,
            'lang' => $language
        ];

        if (Event::handle('StartHtmlElement', [$this, &$attrs])) {
            $this->elementStart('html', $attrs);
            Event::handle('EndHtmlElement', [$this, &$attrs]);
        }
    }

    /**
     *  To specify additional HTTP headers for the action
     *
     * @return void
     */
    public function extraHeaders()
    {
        // Needs to be overloaded
    }

    protected function writeDTD()
    {
        $this->xw->writeDTD(
            $this->DTD['doctype'],
            $this->DTD['spec'],
            $this->DTD['uri']
        );
    }

    public function getLanguage()
    {
        // FIXME: correct language for interface
        return common_language();
    }

    public function setDTD($doctype, $spec, $uri)
    {
        $this->DTD = ['doctype' => $doctype, 'spec' => $spec, 'uri' => $uri];
    }

    /**
     *  Ends an HTML document
     *
     * @return void
     */
    public function endHTML()
    {
        $this->elementEnd('html');
        $this->endXML();
    }

    /**
     * Output an HTML text input element
     *
     * Despite the name, it is specifically for outputting a
     * text input element, not other <input> elements. It outputs
     * a cluster of elements, including a <label> and an associated
     * instructions span.
     *
     * If $attrs['type'] does not exist it will be set to 'text'.
     *
     * @param string $id element ID, must be unique on page
     * @param string $label text of label for the element
     * @param string $value value of the element, default null
     * @param string $instructions instructions for valid input
     * @param string $name name of the element; if null, the id will be used
     * @param bool   $required HTML5 required attribute (exclude when false)
     * @param array  $attrs Initial attributes manually set in an array (overwritten by previous options)
     *
     * @return void
     * @todo add a $maxLength parameter
     * @todo add a $size parameter
     *
     */

    public function input($id, $label, $value = null, $instructions = null, $name = null, $required = false, array $attrs = [])
    {
        $this->element('label', ['for' => $id], $label);
        if (!array_key_exists('type', $attrs)) {
            $attrs['type'] = 'text';
        }
        $attrs['id'] = $id;
        $attrs['name'] = is_null($name) ? $id : $name;
        if (array_key_exists('placeholder', $attrs) && (is_null($attrs['placeholder']) || $attrs['placeholder'] === '')) {
            // If placeholder is type-aware equal to '' or null, unset it as we apparently don't want a placeholder value
            unset($attrs['placeholder']);
        } else {
            // If the placeholder is set use it, or use the label as fallback.
            $attrs['placeholder'] = isset($attrs['placeholder']) ? $attrs['placeholder'] : $label;
        }

        if (!is_null($value)) { // value can be 0 or ''
            $attrs['value'] = $value;
        }
        if (!empty($required)) {
            $attrs['required'] = 'required';
        }
        $this->element('input', $attrs);
        if ($instructions) {
            $this->element('p', 'form_guide', $instructions);
        }
    }

    /**
     * output an HTML checkbox and associated elements
     *
     * Note that the value is default 'true' (the string), which can
     * be used by Action::boolean()
     *
     * @param string $id element ID, must be unique on page
     * @param string $label text of label for the element
     * @param bool   $checked if the box is checked, default false
     * @param string $instructions instructions for valid input
     * @param string $value value of the checkbox, default 'true'
     * @param bool   $disabled show the checkbox disabled, default false
     *
     * @return void
     *
     * @todo add a $name parameter
     */

    public function checkbox(
        $id,
        $label,
        $checked = false,
        $instructions = null,
        $value = 'true',
        $disabled = false
    )
    {
        $attrs = ['name' => $id,
            'type' => 'checkbox',
            'class' => 'checkbox',
            'id' => $id];
        if ($value) {
            $attrs['value'] = $value;
        }
        if ($checked) {
            $attrs['checked'] = 'checked';
        }
        if ($disabled) {
            $attrs['disabled'] = 'true';
        }
        $this->element('input', $attrs);
        $this->text(' ');
        $this->element(
            'label',
            ['class' => 'checkbox',
                'for' => $id],
            $label
        );
        $this->text(' ');
        if ($instructions) {
            $this->element('p', 'form_guide', $instructions);
        }
    }

    /**
     * output an HTML combobox/select and associated elements
     *
     * $content is an array of key-value pairs for the dropdown, where
     * the key is the option value attribute and the value is the option
     * text. (Careful on the overuse of 'value' here.)
     *
     * @param string $id element ID, must be unique on page
     * @param string $label text of label for the element
     * @param array $content options array, value => text
     * @param string $instructions instructions for valid input
     * @param bool $blank_select whether to have a blank entry, default false
     * @param string $selected selected value, default null
     *
     * @return void
     *
     * @todo add a $name parameter
     */

    public function dropdown(
        $id,
        $label,
        $content,
        $instructions = null,
        $blank_select = false,
        $selected = null
    )
    {
        $this->element('label', ['for' => $id], $label);
        $this->elementStart('select', ['id' => $id, 'name' => $id]);
        if ($blank_select) {
            $this->element('option', ['value' => '']);
        }
        foreach ($content as $value => $option) {
            if ($value == $selected) {
                $this->element(
                    'option',
                    ['value' => $value,
                        'selected' => 'selected'],
                    $option
                );
            } else {
                $this->element('option', ['value' => $value], $option);
            }
        }
        $this->elementEnd('select');
        if ($instructions) {
            $this->element('p', 'form_guide', $instructions);
        }
    }

    /**
     * output an HTML hidden element
     *
     * $id is re-used as name
     *
     * @param string $id element ID, must be unique on page
     * @param string $value hidden element value, default null
     * @param string $name name, if different than ID
     *
     * @return void
     */

    public function hidden($id, $value, $name = null)
    {
        $this->element('input', ['name' => $name ?: $id,
            'type' => 'hidden',
            'id' => $id,
            'value' => $value]);
    }

    /**
     * output an HTML password input and associated elements
     *
     * @param string $id element ID, must be unique on page
     * @param string $label text of label for the element
     * @param string $instructions instructions for valid input
     *
     * @return void
     *
     * @todo add a $name parameter
     */

    public function password($id, $label, $instructions = null)
    {
        $this->element('label', ['for' => $id], $label);
        $attrs = ['name' => $id,
            'type' => 'password',
            'class' => 'password',
            'id' => $id];
        $this->element('input', $attrs);
        if ($instructions) {
            $this->element('p', 'form_guide', $instructions);
        }
    }

    /**
     * output an HTML submit input and associated elements
     *
     * @param string $id element ID, must be unique on page
     * @param string $label text of the button
     * @param string $cls class of the button, default 'submit'
     * @param string $name name, if different than ID
     * @param string $title title text for the submit button
     *
     * @return void
     *
     * @todo add a $name parameter
     */

    public function submit($id, $label, $cls = 'submit', $name = null, $title = null)
    {
        $this->element('input', ['type' => 'submit',
            'id' => $id,
            'name' => $name ?: $id,
            'class' => $cls,
            'value' => $label,
            'title' => $title]);
    }

    /**
     * output a script (almost always javascript) tag
     *
     * @param string $src relative or absolute script path
     * @param string $type 'type' attribute value of the tag
     *
     * @return void
     */
    public function script($src, $type = 'text/javascript')
    {
        if (Event::handle('StartScriptElement', [$this, &$src, &$type])) {
            $url = parse_url($src);

            if (empty($url['scheme']) && empty($url['host']) && empty($url['query']) && empty($url['fragment'])) {

                // XXX: this seems like a big assumption

                if (strpos($src, 'plugins/') === 0 || strpos($src, 'local/') === 0) {
                    $src = common_path($src, GNUsocial::isHTTPS()) . '?version=' . GNUSOCIAL_VERSION;
                } else {
                    if (GNUsocial::isHTTPS()) {
                        $server = common_config('javascript', 'sslserver');

                        if (empty($server)) {
                            if (is_string(common_config('site', 'sslserver')) &&
                                mb_strlen(common_config('site', 'sslserver')) > 0) {
                                $server = common_config('site', 'sslserver');
                            } elseif (common_config('site', 'server')) {
                                $server = common_config('site', 'server');
                            }
                            $path = common_config('site', 'path') . '/js/';
                        } else {
                            $path = common_config('javascript', 'sslpath');
                            if (empty($path)) {
                                $path = common_config('javascript', 'path');
                            }
                        }

                        $protocol = 'https';
                    } else {
                        $path = common_config('javascript', 'path');

                        if (empty($path)) {
                            $path = common_config('site', 'path') . '/js/';
                        }

                        $server = common_config('javascript', 'server');

                        if (empty($server)) {
                            $server = common_config('site', 'server');
                        }

                        $protocol = 'http';
                    }

                    if ($path[strlen($path) - 1] != '/') {
                        $path .= '/';
                    }

                    if ($path[0] != '/') {
                        $path = '/' . $path;
                    }

                    $src = $protocol . '://' . $server . $path . $src . '?version=' . GNUSOCIAL_VERSION;
                }
            }

            $this->element(
                'script',
                ['type' => $type,
                    'src' => $src],
                ' '
            );

            Event::handle('EndScriptElement', [$this, $src, $type]);
        }
    }

    /**
     * output a css link
     *
     * @param string $src relative path within the theme directory, or an absolute path
     * @param string $theme 'theme' that contains the stylesheet
     * @param string media         'media' attribute of the tag
     *
     * @return void
     */
    public function cssLink($src, $theme = null, $media = null)
    {
        if (Event::handle('StartCssLinkElement', [$this, &$src, &$theme, &$media])) {
            $url = parse_url($src);
            if (empty($url['scheme']) && empty($url['host']) && empty($url['query']) && empty($url['fragment'])) {
                if (file_exists(Theme::file($src, $theme))) {
                    $src = Theme::path($src, $theme);
                } else {
                    $src = common_path($src, GNUsocial::isHTTPS());
                }
                $src .= '?version=' . GNUSOCIAL_VERSION;
            }
            $this->element('link', ['rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => $src,
                'media' => $media]);
            Event::handle('EndCssLinkElement', [$this, $src, $theme, $media]);
        }
    }

    /**
     * output a style (almost always css) tag with inline
     * code.
     *
     * @param string $code code to put in the style tag
     * @param string $type 'type' attribute value of the tag
     * @param string $media 'media' attribute value of the tag
     *
     * @return void
     */

    public function style($code, $type = 'text/css', $media = null)
    {
        if (Event::handle('StartStyleElement', [$this, &$code, &$type, &$media])) {
            $this->elementStart('style', ['type' => $type, 'media' => $media]);
            $this->raw($code);
            $this->elementEnd('style');
            Event::handle('EndStyleElement', [$this, $code, $type, $media]);
        }
    }

    /**
     * output an HTML textarea and associated elements
     *
     * @param string $id element ID, must be unique on page
     * @param string $label text of label for the element
     * @param string $content content of the textarea, default none
     * @param string $instructions instructions for valid input
     * @param string $name name of textarea; if null, $id will be used
     * @param int $cols number of columns
     * @param int $rows number of rows
     * @param bool $required HTML5 required attribute (exclude when false)
     *
     * @return void
     */

    public function textarea(
        $id,
        $label,
        $content = null,
        $instructions = null,
        $name = null,
        $cols = null,
        $rows = null,
        $required = false
    )
    {
        $this->element('label', ['for' => $id], $label);
        $attrs = [
            'rows' => 3,
            'cols' => 40,
            'id' => $id
        ];
        $attrs['name'] = is_null($name) ? $id : $name;

        if ($cols != null) {
            $attrs['cols'] = $cols;
        }
        if ($rows != null) {
            $attrs['rows'] = $rows;
        }

        if (!empty($required)) {
            $attrs['required'] = 'required';
        }

        $this->element(
            'textarea',
            $attrs,
            $content
        );
        if ($instructions) {
            $this->element('p', 'form_guide', $instructions);
        }
    }

    /**
     * Internal script to autofocus the given element on page onload.
     *
     * @param string $id element ID, must refer to an existing element
     *
     * @return void
     *
     */
    public function autofocus($id)
    {
        $this->inlineScript(
            ' $(document).ready(function() {' .
            ' var el = $("#' . $id . '");' .
            ' if (el.length) { el.focus(); }' .
            ' });'
        );
    }

    /**
     * output a script (almost always javascript) tag with inline
     * code.
     *
     * @param string $code code to put in the script tag
     * @param string $type 'type' attribute value of the tag
     *
     * @return void
     */

    public function inlineScript($code, $type = 'text/javascript')
    {
        if (Event::handle('StartInlineScriptElement', [$this, &$code, &$type])) {
            $this->elementStart('script', ['type' => $type]);
            if ($type == 'text/javascript') {
                $this->raw('/*<![CDATA[*/ '); // XHTML compat
            }
            $this->raw($code);
            if ($type == 'text/javascript') {
                $this->raw(' /*]]>*/'); // XHTML compat
            }
            $this->elementEnd('script');
            Event::handle('EndInlineScriptElement', [$this, $code, $type]);
        }
    }
}
