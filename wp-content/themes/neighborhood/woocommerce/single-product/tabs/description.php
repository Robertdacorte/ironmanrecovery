<?php
/**
 * Description tab
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $woocommerce, $post;

$heading = esc_html( apply_filters('woocommerce_product_description_heading', __( 'Descrição do Produto', 'woocommerce' ) ) );
?>

<h2><?php echo $heading; ?></h2>
<?php
$product_description = get_post_meta($post->ID, 'sf_product_description', true);
// echo do_shortcode($product_description);
the_content();
?>