<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?= do_shortcode(react_get_shortcode(get_the_id())); ?>
    <?php wp_footer(); ?>
</body>
</html>
