<?php
/*
Plugin Name: CSV Posts
Plugin URI: https://www.upwork.com/fl/leonardorsampaio
Description: Inserir posts em lote a partir de um arquivo CSV com colunas TAG_NOME e zero ou mais TAG_IMAGEM
Author: Leonardo Sampaio
Version: 1.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

function convert_cp1252_to_utf8($input, $default = '', $replace = array()) {
    if ($input === null || $input == '') {
        return $default;
    }

    // https://en.wikipedia.org/wiki/UTF-8
    // https://en.wikipedia.org/wiki/ISO/IEC_8859-1
    // https://en.wikipedia.org/wiki/Windows-1252
    // http://www.unicode.org/Public/MAPPINGS/VENDORS/MICSFT/WINDOWS/CP1252.TXT
    $encoding = mb_detect_encoding($input, array('Windows-1252', 'ISO-8859-1'), true);
    if ($encoding == 'ISO-8859-1' || $encoding == 'Windows-1252') {
        /*
         * Use the search/replace arrays if a character needs to be replaced with
         * something other than its Unicode equivalent.
         */ 

        /*$replace = array(
            128 => "&#x20AC;",      // http://www.fileformat.info/info/unicode/char/20AC/index.htm EURO SIGN
            129 => "",              // UNDEFINED
            130 => "&#x201A;",      // http://www.fileformat.info/info/unicode/char/201A/index.htm SINGLE LOW-9 QUOTATION MARK
            131 => "&#x0192;",      // http://www.fileformat.info/info/unicode/char/0192/index.htm LATIN SMALL LETTER F WITH HOOK
            132 => "&#x201E;",      // http://www.fileformat.info/info/unicode/char/201e/index.htm DOUBLE LOW-9 QUOTATION MARK
            133 => "&#x2026;",      // http://www.fileformat.info/info/unicode/char/2026/index.htm HORIZONTAL ELLIPSIS
            134 => "&#x2020;",      // http://www.fileformat.info/info/unicode/char/2020/index.htm DAGGER
            135 => "&#x2021;",      // http://www.fileformat.info/info/unicode/char/2021/index.htm DOUBLE DAGGER
            136 => "&#x02C6;",      // http://www.fileformat.info/info/unicode/char/02c6/index.htm MODIFIER LETTER CIRCUMFLEX ACCENT
            137 => "&#x2030;",      // http://www.fileformat.info/info/unicode/char/2030/index.htm PER MILLE SIGN
            138 => "&#x0160;",      // http://www.fileformat.info/info/unicode/char/0160/index.htm LATIN CAPITAL LETTER S WITH CARON
            139 => "&#x2039;",      // http://www.fileformat.info/info/unicode/char/2039/index.htm SINGLE LEFT-POINTING ANGLE QUOTATION MARK
            140 => "&#x0152;",      // http://www.fileformat.info/info/unicode/char/0152/index.htm LATIN CAPITAL LIGATURE OE
            141 => "",              // UNDEFINED
            142 => "&#x017D;",      // http://www.fileformat.info/info/unicode/char/017d/index.htm LATIN CAPITAL LETTER Z WITH CARON 
            143 => "",              // UNDEFINED
            144 => "",              // UNDEFINED
            145 => "&#x2018;",      // http://www.fileformat.info/info/unicode/char/2018/index.htm LEFT SINGLE QUOTATION MARK 
            146 => "&#x2019;",      // http://www.fileformat.info/info/unicode/char/2019/index.htm RIGHT SINGLE QUOTATION MARK
            147 => "&#x201C;",      // http://www.fileformat.info/info/unicode/char/201c/index.htm LEFT DOUBLE QUOTATION MARK
            148 => "&#x201D;",      // http://www.fileformat.info/info/unicode/char/201d/index.htm RIGHT DOUBLE QUOTATION MARK
            149 => "&#x2022;",      // http://www.fileformat.info/info/unicode/char/2022/index.htm BULLET
            150 => "&#x2013;",      // http://www.fileformat.info/info/unicode/char/2013/index.htm EN DASH
            151 => "&#x2014;",      // http://www.fileformat.info/info/unicode/char/2014/index.htm EM DASH
            152 => "&#x02DC;",      // http://www.fileformat.info/info/unicode/char/02DC/index.htm SMALL TILDE
            153 => "&#x2122;",      // http://www.fileformat.info/info/unicode/char/2122/index.htm TRADE MARK SIGN
            154 => "&#x0161;",      // http://www.fileformat.info/info/unicode/char/0161/index.htm LATIN SMALL LETTER S WITH CARON
            155 => "&#x203A;",      // http://www.fileformat.info/info/unicode/char/203A/index.htm SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
            156 => "&#x0153;",      // http://www.fileformat.info/info/unicode/char/0153/index.htm LATIN SMALL LIGATURE OE
            157 => "",              // UNDEFINED
            158 => "&#x017e;",      // http://www.fileformat.info/info/unicode/char/017E/index.htm LATIN SMALL LETTER Z WITH CARON
            159 => "&#x0178;",      // http://www.fileformat.info/info/unicode/char/0178/index.htm LATIN CAPITAL LETTER Y WITH DIAERESIS
        );*/

        if (count($replace) != 0) {
            $find = array();
            foreach (array_keys($replace) as $key) {
                $find[] = chr($key);
            }
            $input = str_replace($find, array_values($replace), $input);
        }
        /*
         * Because ISO-8859-1 and CP1252 are identical except for 0x80 through 0x9F
         * and control characters, always convert from Windows-1252 to UTF-8.
         */
        $input = iconv('Windows-1252', 'UTF-8//IGNORE', $input);
        if (count($replace) != 0) {
            $input = html_entity_decode($input);
        }
    }
    return $input;
}

function csv_meta_box_add()
{
    global $post;
    if (isset($post) && $post->post_status == 'publish')
    {
        //show only in new/private/draft
        return;
    }

    add_meta_box(
        'csv-posts-meta',
        'CSV Posts',
        'csv_meta_box_html',
        $post->post_type,
        'normal',
        'high');
}

function csv_meta_box_html( $post )
{
    wp_nonce_field( '_csv_posts_nonce', 'csv_posts_nonce' ); ?>
    <p>Para publicar posts em lote, informe um arquivo CSV com uma coluna TAG_NOME e
    zero ou mais colunas TAG_IMAGEM, que serão substituídas no texto. Cada linha corresponderá a um novo post.</p>
    <label for="csv-posts-visibily">Visibilidade</label>
    <select id="csv-posts-visibily" name="csv-posts-visibily">
        <option value="publish">Público</option>
        <option value="private">Privado</option>
        <option value="draft">Rascunho</option>
    </select>
    <br/>
    <label for="csv-posts-encoding">Codificação</label>
    <select id="csv-posts-encoding" name="csv-posts-encoding">
        <option value="utf8">UTF-8</option>
        <option value="windows1252">Windows-1252</option>
    </select>
    <br/>
    <label for="csv">Arquivo CSV</label>
    <input type="file" id="csv-input" name="csv-input" accept=".csv">
    <?php
}

function hook_transition_post_status($new_status, $old_status, $post)
{
    set_transient('csv-count', null, 100);
    
    if((!isset($_POST['csv_import']) && !$_POST['csv_import']) &&
    in_array($new_status,['draft','private']) && 
    isset($_FILES['csv-input']) && 
    isset($_FILES["csv-input"]["tmp_name"]))
    {
        $yoastActive = (is_plugin_active( 'wordpress-seo/wp-seo.php' ) || 
            is_plugin_active( 'wordpress-seo-premium1/wp-seo-premium.php' ));

        $count = 0;

        $csvFileHandler = fopen($_FILES["csv-input"]["tmp_name"],"r");
        if ($csvFileHandler)
        {
            $postsVisibility = $_POST['csv-posts-visibily'];
            $encoding = isset($_POST['csv-posts-encoding']) ? $_POST['csv-posts-encoding'] : null;

            
            while (($line = fgets($csvFileHandler)) !== false) {

                if ($encoding != 'utf8')
                {
                    $line = convert_cp1252_to_utf8($line);
                }

                $separator = strpos($line,',') ? ',' : ';';
                $csvLineArray = explode($separator, $line);

                if ($csvLineArray)
                {
                    $productName = $csvLineArray[0];
                    $productImages = $csvLineArray[1] ? array_slice($csvLineArray,1) : null;

                    $replacedContent = $post->post_content;

                    if ($productImages)
                    {
                        preg_match_all('/src="(.*)?" alt="(.*TAG_NOME.*?)"/', $post->post_content, $imgs);

                        if ($imgs)
                        {
                            foreach($imgs[1] as $k => $img)
                            {
                                $productImage = $productImages[$k] ? $productImages[$k] : $productImages[0];
                                $replacedContent = str_replace($img, trim($productImage), $replacedContent);
                            }
                        }
                    }
    
                    date_default_timezone_set('America/Sao_Paulo');
                    $dateStr = date('Y-m-d H:i:s');
    
                    $replacedContent = str_replace('TAG_NOME', $productName, $replacedContent);
                    
                    $replacedTitle = str_replace('TAG_NOME', $productName, $post->post_title);
    
                    $replacedName = preg_replace("/[^A-Za-z0-9-]/", '', 
                        str_replace(' ', '-', $productName));

                    $postName = str_replace('tag_nome', $replacedName, strtolower($post->post_name));

                    $newPost = [
                        "post_type"             =>  $post->post_type,
                        "post_title"            =>  $replacedTitle,
                        "post_name"             =>  $postName,
                        "post_content"          =>  $replacedContent,
                        "post_status"           =>  $postsVisibility,
                        "post_date"             =>  $dateStr,
                        "post_modified"         =>  $dateStr
                    ];

                    $_POST['csv_import'] = true;

                    $id = wp_insert_post($newPost, true, true);
                    if (is_int($id))
                    {
                        if ($yoastActive)
                        {
                            $metaTitle = str_replace('TAG_NOME', $productName, $_POST['yoast_wpseo_title']);
                            update_post_meta($id, '_yoast_wpseo_title', $metaTitle);

                            $metaDescription = str_replace('TAG_NOME', $productName, $_POST['yoast_wpseo_metadesc']);
                            update_post_meta($id, '_yoast_wpseo_metadesc',  $metaDescription);
                            
                            $metaKeyword = str_replace('TAG_NOME', $productName, $_POST['yoast_wpseo_focuskw']);
                            update_post_meta($id, '_yoast_wpseo_focuskw',   $metaKeyword);
                        }
                        $count++;
                    }
                }
            }
            fclose($csvFileHandler);
            set_transient('csv-count', [$count, $postsVisibility], 100);
        }
    }
}

function admin_notice() {
    $screen = get_current_screen();

    if ( ! $screen || 'post' !== $screen->base ) {
        return;
    }

    $count = get_transient('csv-count');

    if (!$count || 
        !$count[0] || 
        !$count[1] || 
        $count[0] === 0)
    {
        return;
    }

    $multiple = $count[0] > 1 ? 's' : '';

    echo '<div class="notice notice-success is-dismissible"><p>';
    echo sprintf(__( '%s post%s publicado%s como %s a partir do arquivo CSV' ),
        $count[0],
        $multiple,
        $multiple,
        ($count[1] === 'publish' ? 'Público' : 
            ($count[1] === 'private' ? 'Privado' : 
                'Rascunho'))
        );
    echo '</p></div>';

    set_transient('csv-count', null, 100);
}

function add_post_multipart_form_data() {
    echo ' enctype="multipart/form-data"';
}

function save_postdata( $post_id ) {
}

function save_draftdata( $post_id ) {
}

add_action('admin_notices', 'admin_notice');
add_action('post_edit_form_tag', 'add_post_multipart_form_data');
add_action('save_draft', 'save_draftdata');
add_action('save_post', 'save_postdata');
add_action('add_meta_boxes', 'csv_meta_box_add');
add_action('transition_post_status', 'hook_transition_post_status', 10, 3);