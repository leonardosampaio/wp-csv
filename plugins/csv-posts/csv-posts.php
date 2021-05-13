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

            while (($line = fgets($csvFileHandler)) !== false) {
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