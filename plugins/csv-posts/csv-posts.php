<?php
/*
Plugin Name: CSV Posts
Plugin URI: https://www.upwork.com/fl/leonardorsampaio
Description: Inserir posts em lote a partir de um arquivo CSV com colunas TAG_NOME e TAG_IMAGEM
Author: Leonardo Sampaio
Version: 1.0
*/

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
            'html',
            'post',
            'normal',
            'high');
}

function html( $post )
{
    wp_nonce_field( '_csv_posts_nonce', 'csv_posts_nonce' ); ?>
    <p>Para publicar posts em lote, informe um arquivo CSV com uma coluna TAG_NOME e
    uma ou mais colunas TAG_IMAGEM, que serão substituídas no texto.</p>
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

function admin_head_js() {
echo "
<script>
    window.addEventListener('load', (event) => {
    document.getElementById('csv-input').onchange = function(e) {
            console.log(e);
    };
    });
</script>
";
}

function hook_transition_post_status($new_status, $old_status, $post)
{
    if((!isset($_POST['csv_import']) && !$_POST['csv_import']) &&
        in_array($new_status,['draft','private']) && 
        isset($_FILES['csv-input']) && 
        isset($_FILES["csv-input"]["tmp_name"]))
    {
        $count = 0;
        set_transient('csv-count', 0, 100);

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
                                $replacedContent = str_replace($img, $productImage, $replacedContent);
                            }
                        }
                    }
    
                    date_default_timezone_set('America/Sao_Paulo');
                    $dateStr = date('Y-m-d H:i:s');
    
                    $replacedContent = str_replace('TAG_NOME', $productName, $replacedContent);
                    
                    $replacedTitle = str_replace('TAG_NOME', $productName, $post->post_title);
    
                    $replacedName = preg_replace("/[^A-Za-z0-9_]/", '', 
                        str_replace(' ', '_', $productName));

                    $newPost = [
                        "post_title"    =>  $replacedTitle,
                        "post_name"     =>  $replacedName,
                        "post_content"  =>  $replacedContent,
                        "post_status"   =>  $postsVisibility,
                        "post_date"     =>  $dateStr,
                        "post_modified" =>  $dateStr
                    ];

                    $_POST['csv_import'] = true;
                    if (is_int(wp_insert_post($newPost, true, true)))
                    {
                        $count++;
                    }
                }
            }
            fclose($csvFileHandler);
            set_transient('csv-count', $count, 100);
        }
    }
}

function admin_notice() {
    $screen = get_current_screen();

    if ( ! $screen || 'post' !== $screen->base ) {
        return;
    }

    $count = get_transient('csv-count');

    if (!$count || $count === 0)
    {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>';
    echo sprintf( __( '%s posts publicados a partir do arquivo CSV' ), $count );
    echo '</p></div>';

    set_transient('csv-count', 0, 100);
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
add_action('admin_head', 'admin_head_js');
add_action('transition_post_status', 'hook_transition_post_status', 10, 3);