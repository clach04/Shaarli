<?php

/**
 * Plugin Markdown.
 *
 * Shaare's descriptions are parsed with Markdown.
 */

/*
 * If this tag is used on a shaare, the description won't be processed by Parsedown.
 */
define('NO_MD_TAG', 'nomarkdown');

/**
 * Parse linklist descriptions.
 *
 * @param array         $data linklist data.
 * @param ConfigManager $conf instance.
 *
 * @return mixed linklist data parsed in markdown (and converted to HTML).
 */
function hook_markdown_render_linklist($data, $conf)
{
    foreach ($data['links'] as &$value) {
        if (!empty($value['tags']) && noMarkdownTag($value['tags'])) {
            $value = stripNoMarkdownTag($value);
            continue;
        }
        $value['description'] = process_markdown(
            $value['description'],
            $conf->get('security.markdown_escape', true),
            $conf->get('security.allowed_protocols')
        );
    }
    return $data;
}

/**
 * Parse feed linklist descriptions.
 *
 * @param array $data linklist data.
 * @param ConfigManager $conf instance.
 *
 * @return mixed linklist data parsed in markdown (and converted to HTML).
 */
function hook_markdown_render_feed($data, $conf)
{
    foreach ($data['links'] as &$value) {
        if (!empty($value['tags']) && noMarkdownTag($value['tags'])) {
            $value = stripNoMarkdownTag($value);
            continue;
        }
        $value['description'] = process_markdown(
            $value['description'],
            $conf->get('security.markdown_escape', true),
            $conf->get('security.allowed_protocols')
        );
    }

    return $data;
}

/**
 * Parse daily descriptions.
 *
 * @param array         $data daily data.
 * @param ConfigManager $conf instance.
 *
 * @return mixed daily data parsed in markdown (and converted to HTML).
 */
function hook_markdown_render_daily($data, $conf)
{
    //var_dump($data);die;
    // Manipulate columns data
    foreach ($data['linksToDisplay'] as &$value) {
        if (!empty($value['tags']) && noMarkdownTag($value['tags'])) {
            $value = stripNoMarkdownTag($value);
            continue;
        }
        $value['formatedDescription'] = process_markdown(
            $value['formatedDescription'],
            $conf->get('security.markdown_escape', true),
            $conf->get('security.allowed_protocols')
        );
    }

    return $data;
}

/**
 * Check if noMarkdown is set in tags.
 *
 * @param string $tags tag list
 *
 * @return bool true if markdown should be disabled on this link.
 */
function noMarkdownTag($tags)
{
    return preg_match('/(^|\s)'. NO_MD_TAG .'(\s|$)/', $tags);
}

/**
 * Remove the no-markdown meta tag so it won't be displayed.
 *
 * @param array $link Link data.
 *
 * @return array Updated link without no markdown tag.
 */
function stripNoMarkdownTag($link)
{
    if (! empty($link['taglist'])) {
        $offset = array_search(NO_MD_TAG, $link['taglist']);
        if ($offset !== false) {
            unset($link['taglist'][$offset]);
        }
    }

    if (!empty($link['tags'])) {
        str_replace(NO_MD_TAG, '', $link['tags']);
    }

    return $link;
}

/**
 * When link list is displayed, include markdown CSS.
 *
 * @param array $data includes data.
 *
 * @return mixed - includes data with markdown CSS file added.
 */
function hook_markdown_render_includes($data)
{
    if ($data['_PAGE_'] == Router::$PAGE_LINKLIST
        || $data['_PAGE_'] == Router::$PAGE_DAILY
        || $data['_PAGE_'] == Router::$PAGE_EDITLINK
    ) {

        $data['css_files'][] = PluginManager::$PLUGINS_PATH . '/markdown/markdown.css';
    }

    return $data;
}

/**
 * Hook render_editlink.
 * Adds an help link to markdown syntax.
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_markdown_render_editlink($data)
{
    // Load help HTML into a string
    $txt = file_get_contents(PluginManager::$PLUGINS_PATH .'/markdown/help.html');
    $translations = [
        t('Description will be rendered with'),
        t('Markdown syntax documentation'),
        t('Markdown syntax'),
    ];
    $data['edit_link_plugin'][] = vsprintf($txt, $translations);
    // Add no markdown 'meta-tag' in tag list if it was never used, for autocompletion.
    if (! in_array(NO_MD_TAG, $data['tags'])) {
        $data['tags'][NO_MD_TAG] = 0;
    }

    return $data;
}


/**
 * Remove HTML links auto generated by Shaarli core system.
 * Keeps HREF attributes.
 *
 * @param string $description input description text.
 *
 * @return string $description without HTML links.
 */
function reverse_text2clickable($description)
{
    $descriptionLines = explode(PHP_EOL, $description);
    $descriptionOut = '';
    $codeBlockOn = false;
    $lineCount = 0;

    foreach ($descriptionLines as $descriptionLine) {
        // Detect line of code: starting with 4 spaces,
        // except lists which can start with +/*/- or `2.` after spaces.
        $codeLineOn = preg_match('/^    +(?=[^\+\*\-])(?=(?!\d\.).)/', $descriptionLine) > 0;
        // Detect and toggle block of code
        if (!$codeBlockOn) {
            $codeBlockOn = preg_match('/^```/', $descriptionLine) > 0;
        }
        elseif (preg_match('/^```/', $descriptionLine) > 0) {
            $codeBlockOn = false;
        }

        $hashtagTitle = ' title="Hashtag [^"]+"';
        // Reverse `inline code` hashtags.
        $descriptionLine = preg_replace(
            '!(`[^`\n]*)<a href="[^ ]*"'. $hashtagTitle .'>([^<]+)</a>([^`\n]*`)!m',
            '$1$2$3',
            $descriptionLine
        );

        // Reverse all links in code blocks, only non hashtag elsewhere.
        $hashtagFilter = (!$codeBlockOn && !$codeLineOn) ? '(?!'. $hashtagTitle .')': '(?:'. $hashtagTitle .')?';
        $descriptionLine = preg_replace(
            '#<a href="[^ ]*"'. $hashtagFilter .'>([^<]+)</a>#m',
            '$1',
            $descriptionLine
        );

        $descriptionOut .= $descriptionLine;
        if ($lineCount++ < count($descriptionLines) - 1) {
            $descriptionOut .= PHP_EOL;
        }
    }
    return $descriptionOut;
}

/**
 * Remove <br> tag to let markdown handle it.
 *
 * @param string $description input description text.
 *
 * @return string $description without <br> tags.
 */
function reverse_nl2br($description)
{
    return preg_replace('!<br */?>!im', '', $description);
}

/**
 * Remove HTML spaces '&nbsp;' auto generated by Shaarli core system.
 *
 * @param string $description input description text.
 *
 * @return string $description without HTML links.
 */
function reverse_space2nbsp($description)
{
    return preg_replace('/(^| )&nbsp;/m', '$1 ', $description);
}

/**
 * Replace not whitelisted protocols with http:// in given description.
 *
 * @param string $description      input description text.
 * @param array  $allowedProtocols list of allowed protocols.
 *
 * @return string $description without malicious link.
 */
function filter_protocols($description, $allowedProtocols)
{
    return preg_replace_callback(
        '#]\((.*?)\)#is',
        function ($match) use ($allowedProtocols) {
            return ']('. whitelist_protocols($match[1], $allowedProtocols) .')';
        },
        $description
    );
}

/**
 * Remove dangerous HTML tags (tags, iframe, etc.).
 * Doesn't affect <code> content (already escaped by Parsedown).
 *
 * @param string $description input description text.
 *
 * @return string given string escaped.
 */
function sanitize_html($description)
{
    $escapeTags = array(
        'script',
        'style',
        'link',
        'iframe',
        'frameset',
        'frame',
    );
    foreach ($escapeTags as $tag) {
        $description = preg_replace_callback(
            '#<\s*'. $tag .'[^>]*>(.*</\s*'. $tag .'[^>]*>)?#is',
            function ($match) { return escape($match[0]); },
            $description);
    }
    $description = preg_replace(
        '#(<[^>]+\s)on[a-z]*="?[^ "]*"?#is',
        '$1',
        $description);
    return $description;
}

/**
 * Render shaare contents through Markdown parser.
 *   1. Remove HTML generated by Shaarli core.
 *   2. Reverse the escape function.
 *   3. Generate markdown descriptions.
 *   4. Sanitize sensible HTML tags for security.
 *   5. Wrap description in 'markdown' CSS class.
 *
 * @param string $description input description text.
 * @param bool   $escape      escape HTML entities
 *
 * @return string HTML processed $description.
 */
function process_markdown($description, $escape = true, $allowedProtocols = [])
{
    $parsedown = new Parsedown();

    $processedDescription = $description;
    $processedDescription = reverse_nl2br($processedDescription);
    $processedDescription = reverse_space2nbsp($processedDescription);
    $processedDescription = reverse_text2clickable($processedDescription);
    $processedDescription = filter_protocols($processedDescription, $allowedProtocols);
    $processedDescription = unescape($processedDescription);
    $processedDescription = $parsedown
        ->setMarkupEscaped($escape)
        ->setBreaksEnabled(true)
        ->text($processedDescription);
    $processedDescription = sanitize_html($processedDescription);

    if(!empty($processedDescription)){
        $processedDescription = '<div class="markdown">'. $processedDescription . '</div>';
    }

    return $processedDescription;
}

/**
 * This function is never called, but contains translation calls for GNU gettext extraction.
 */
function markdown_dummy_translation()
{
    // meta
    t('Render shaare description with Markdown syntax.<br><strong>Warning</strong>:
If your shaared descriptions contained HTML tags before enabling the markdown plugin,
enabling it might break your page.
See the <a href="https://github.com/shaarli/Shaarli/tree/master/plugins/markdown#html-rendering">README</a>.');
}
