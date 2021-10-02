<?php
/**
 * @package React Apps
 * @version 1.0.0
 */
/*
Plugin Name: Embed React App
Description: Embed your react app in wordpress or create single page react app.
Author: Safouane Ahmed Salah
Version: 1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* react custom post type */
add_action('init', 'react_create_post_type'); 
function react_create_post_type() {
    $args = array(
        'labels' => array('name' => __('React Apps'), 'singular_name' => __('React App')),
        'public' => true, /* shows in admin on left menu etc */    
        'publicly_queryable' => true, 
        'menu_icon'=> '//img.icons8.com/officel/16/000000/react.png',
        'supports' => array('title'),  
    );
    register_post_type('react', $args);
}

//get assets
add_action( 'template_redirect', 'react_template_redirect' );
function react_template_redirect(){
  global $wp;
  if(preg_match('@^react/([\w-]+)/assets/(.*)@', $wp->request, $m)){
    $slug = @$m[1];
    $file = @$m[2];
    $post = get_page_by_path($slug, OBJECT,'react');
    if($post){
      $assets= get_post_meta($post->ID, 'assets', true);
      $mime = wp_check_filetype($file);
      $content = @$assets[$file];

      if($content){
        status_header(200);
        header('Content-Type: '. $mime['type']);
        echo base64_decode($content);
        exit;
      }
    }
    
  }
}

add_filter('single_template', 'react_template'); 
function react_template() {
    global $post;

    if( $post->post_type == 'react') {
        if(get_post_meta($post->ID, 'disabled', true)) return get_404_template();
        return __DIR__.'/page.php';
    }

    return $single;
}

function react_get_shortcode($id){
  return '[react-app id="'. $id .'"]';
}
function react_get_html_id($id){
  return 'reactapp-'. $id;
}
//end


add_shortcode('react-app', 'react_shortcode');
function react_shortcode($atts){
  extract(shortcode_atts(['id'=> 0], $atts));

  if(!$id) return;
  $assets = (array)get_post_meta($id, 'assets', true); 
  $path = get_permalink($id) . 'assets/';

  foreach($assets as $file => $asset){
    if(preg_match('/\.js$/i', $file)){
      wp_enqueue_script('react-script-'.md5($id.$file) , $path.$file, [], '1.0.0', true);
    }
    if(preg_match('/\.css$/i', $file)){
      wp_enqueue_style('react-style-'.md5($id.$file) , $path.$file);
    }
  }

  return '<div id="'. react_get_html_id($id) .'"></div>';
}


//admin part
add_action("admin_init", "react_admin_init");
function react_admin_init(){
    add_meta_box("react-build-file", "Build", "react_build", "react", "side");
    add_meta_box("react-shortcode", "Shortcode", "react_admin_shortcode", "react", "side");
    add_meta_box("react-instruction", "Instruction", "react_instruction", "react", 'advanced', 'high');
    add_meta_box("react-setting", "Setting", "react_setting", "react");
    add_meta_box("react-preview", "Preview", "react_preview", "react");
} 

//add column shortcode
add_filter( 'manage_react_posts_columns', 'react_shortcode_column' );
function react_shortcode_column($columns) {
    $columns['shortcode'] = 'Shortcode';
    return $columns;
}
add_filter( 'manage_react_posts_custom_column', 'react_shortcode_column_echo', 10, 2);
function react_shortcode_column_echo($column, $postId) {
    if($column=='shortcode') echo react_admin_shortcode(get_post($postId));
}
//end column shortcode


function react_instruction($post){
  echo '<style>
  #react-instruction code{border: 1px solid;white-space: pre;background: #000;display: block;margin:5px 0;color:orange;border-radius:4px }
  </style><div class="react-instruction">
   <div>To get this working. before you build your app, you need to follow the bellow steps:</div>
   <ol>
    <li><b>npm i react-app-rewired --save-dev</b></li>
    <li>Create config-overrides.js file in root directory or react app with content<br>
    <code type="js">
        module.exports = function override(config, env) {
          config.optimization.splitChunks = {
            cacheGroups: {
              default: false,
            },
          };
          config.optimization.runtimeChunk = false;
          return config;
      }
    </code>
    </li>
    <li>Change script in package.json to <br><code>
    "start": "react-app-rewired start",
    "build": "react-app-rewired build",
    "test": "react-app-rewired test",
    </code> </li>
    <li> root id: <code>'.react_get_html_id($post->ID).'</code><small>set dom access id in src/index.js and public/index.html</small></li>
    <li>Build your react-app. <br>
      - <code>npm run build</code><br>
      - zip the build folder<br>
      - upload the build here
    </li>
   </ol>
  </div>';
}

function react_setting($post){
  $checked = $post && get_post_meta($post->ID, 'disabled', true);
  $rootId = $post ? get_post_meta($post->ID, 'root_id', true) : '';
  if(!$rootId) $rootId='root';
  echo '<table style="width:100%">
    <tr><td>Disable Page: </td><td><input type="checkbox" name="disabled" '.($checked ? 'checked' : '').' /></td></tr>
  </table>';
}

//input build
function react_build(){
  echo '<input type="file" id="build_file"  accept=".zip" name="file" /><script>
      var file = document.getElementById("build_file");
      file.form.enctype = "multipart/form-data";
  </script>';
}

function react_admin_shortcode($post){
  echo '<input readonly onfocus="this.select()" style="width:100%;padding:5px" value="'. esc_attr(react_get_shortcode($post->ID)) .'"/>';
}

function react_preview($post){
  echo do_shortcode(react_get_shortcode($post->ID));
} 

add_action('save_post_react', 'react_deploy');
function react_deploy($postId){
  //disabled
  update_post_meta($postId, 'disabled', isset($_POST['disabled']) ? 1 : 0);

  //file build
  $tmp_file = @$_FILES['file']['tmp_name'];
  if(!$tmp_file) return;

  $zip = new ZipArchive;
  if($zip->open($tmp_file)) {
      $assets = [];
      for ($idx = 0; $zipFile = $zip->statIndex($idx); $idx++) { 
          if ($zipFile['size']) {
              $contents = $zip->getFromIndex($idx);
              $key = str_replace('build/', '', $zipFile['name']);
              $assets[$key] = base64_encode($contents);
          }
      }
      update_post_meta($postId, 'assets', $assets);  
  }
}