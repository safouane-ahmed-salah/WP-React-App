<?php
/**
 * @package React Apps
 * @version 1.0.0
 */
/*
Plugin Name: React App shortcode or single page
Description: Embed your react app in wordpress or create single page react app.
Author: Safouane Ahmed Salah
Version: 1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('REACT_BUILD_DIR', __DIR__.'/releases/');

/* react custom post type */
add_action('init', 'react_create_post_type'); 
function react_create_post_type() {
    $args = array(
        'labels' => array('name' => __('React Apps'), 'singular_name' => __('React App')),
        'public' => true, /* shows in admin on left menu etc */    
        'publicly_queryable' => true, 
        'menu_icon'=> '//img.icons8.com/officel/16/000000/react.png',
        'supports' => array( 'title'),  
    );
    register_post_type('react', $args);
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

//functions
function react_get_build_dir($postId){
    return REACT_BUILD_DIR.'/'.$postId.'/build';
}
function react_get_manifest($id, $key){
  $manifest_file = react_get_build_dir($id).'/asset-manifest.json';
  if(!file_exists($manifest_file)) return;
  $manifest = file_get_contents($manifest_file);
  $json = json_decode($manifest, true);
  $files = $json['files'];

  return @$files[$key];
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
  $js = react_get_manifest($id, 'main.js');
  $css = react_get_manifest($id, 'main.css');
  if(!$js || !$css) return;

  wp_enqueue_style('react-style-'.$id, $css);
  wp_enqueue_script('react-script-'.$id, $js, [], '1.0.0', true);

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
  if(!$post) return print('Must save to view instruction');

  echo '<div>
   <div>To get this working you need to follow the bellow steps steps:</div>
   <ol>
    <li><b>npm i react-app-rewired --save-dev</b>/li>
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
    <li>add homepage to package.json to <br><code>
    "homepage": "'.plugins_url('/',react_get_build_dir($post->ID).'/test.ss').'",
    </code> </li>
    <li> root id: <b>'.react_get_html_id($post->ID).'</b> <br><small>set dom access id in src/index.js and public/index.html</small></li>
    <li>after you create your react-app. <br>
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
  if(!$post) return;
  echo '<input readonly onfocus="this.select()" style="width:100%;padding:5px" value="'. esc_attr(react_get_shortcode($post->ID)) .'"/>';
}

function react_preview($post){
  if(!$post) return;
  echo do_shortcode(react_get_shortcode($post->ID));
} 

add_action('save_post_react', 'react_deploy');
function react_deploy($postId){

  //disabled
  update_post_meta($postId, 'disabled', isset($_POST['disabled']) ? 1 : 0);

  //file build
  $file = @$_FILES['file'];
  if(!$file) return;
  if(!function_exists( 'WP_Filesystem' ) ) {
    include_once ABSPATH.'/wp-admin/includes/file.php';
  }
  WP_Filesystem();
  
  $tmp_file = @$file['tmp_name'];
  if(!$tmp_file) return;
  
  $path = REACT_BUILD_DIR.'/'. $postId . '/';
  wp_mkdir_p($path);
  unzip_file( $tmp_file, $path);
}