<?php

/*
Plugin Name: HM Swap Tax
Description: Move/copy posts into different terms/taxonomies
Version: 1.0
Author: Human Made Limited
Author URI: http://hmn.md/
*/

//Create the admin submenu page and draw it inline
add_action( 'admin_menu', function() {

	add_submenu_page( 'tools.php', 'Change Taxonomies', 'Change Taxonomies', 'administrator', 'change_tax', function() {
		?>
				
		<div class="wrap">
			
			<?php if ( ! empty( $_GET['status'] ) ) : ?>
				<?php if ( $_GET['status'] == 'done' ) : ?>
					<div class="updated message">
						<p>Completed</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<div id="icon-tools" class="icon32"><br></div>
			<h2>Change Taxonomies</h2>
			
			<div class="widefat hmct">

				<?php $taxonomies = get_taxonomies( array(), 'objects' ); ?>
					
				<form class="hmct-form" method="post">
				
					<div class="hmct-from hmct-tax-container" styke="overflow:hidden">
							
							<h2>From</h2><br />
					    	
					    	<select data-multiple-terms="1" name="hmct-from-taxonomies" class="hmct-tax-selector" data-direction="from" style="float:left">	
					    			
					    		<option value=""> - Taxonomy - </option>	
					    			
					    		<?php foreach ( $taxonomies as $key => $tax ): ?>
					    	
					    			<option <?php selected( ! empty( $_GET['taxonomy_from'] ) && $_GET['taxonomy_from'] == $key ) ?> value="<?php echo $key; ?>"><?php echo $tax->labels->name; ?></option>
					    
					    		<?php endforeach; ?>
					    		
					    	</select>
					    		
					</div>
					
					<div class="hmct-to hmct-tax-container">
					    	
					    	<h2>To</h2><br />
					    	
					    	<select name="hmct-to-taxonomies" class="hmct-tax-selector" data-direction="to" >
					    		
					    		<option value=""> - Taxonomy - </option>	
					    			
					    		<?php foreach ( $taxonomies as $key => $tax ): ?>
					    
					    			<option <?php selected( ! empty( $_GET['taxonomy_to'] ) && $_GET['taxonomy_to'] == $key ) ?> value="<?php echo $key; ?>"><?php echo $tax->labels->name; ?></option>
					
					    		<?php endforeach; ?>
					    		
					    	</select>
					
					</div>		
					
					<div class="hmct-option-container">
						
						<h2>Options</h2><br />
					
						<label for="hmct-insertion-method-add">Append term</label>	
						<input type="checkbox" id="hmct-insertion-method-add" name="hmct-insertion-method" value="1" checked />
						
						<label for="hmct-delete-origine">Delete from origin term</label>	
						<input type="checkbox" id="hmct-delete-origin" name="hmct-delete-origin" value="1" />
			
					
					</div>
					
					<p>
						
						<input type="submit" class="button-primary" value="Submit" />
					
					</p>
					
				</form>
			
			</div>
			
		</div>	
		
		<?php
	} );

} );

//Do the swappage
function hmct_swap_them( $args = array() ) {
	
	$defaults = array( 
		
		'from_tax' 		=> null,
		'to_tax' 		=> null,
		'from_term' 	=> array(),
		'to_term'		=> null,
		'append'		=> false,
		'delete_origin' => false
		
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	$args['from_term'] = (array) $args['from_term'];
		
	if ( ! $args['from_tax'] || ! $args['to_tax'] || ! $args['from_term'] || ! $args['to_term'] )
		return false;
	
	$posts = get_posts( array( 
	
		'post_type' => 'any',
		'tax_query' => array(
			array(
				'taxonomy' => $args['from_tax'],
				'field' => 'id',
				'terms' => $args['from_term']
			)
		),
		
		'posts_per_page' => 0,
		'nopaging' => true,
		'post_status' => 'all'
	) );

	foreach ( $posts as $key => $post ) { 
		
		$term_ids = array();
		
		if ( $args['delete_origin'] ) {
			
			$terms = wp_get_object_terms( $post->ID, $args['from_tax'] );
		
			foreach ( $terms as $term ) {
		
				 if ( in_array( $term->term_id, $args['from_term'] ) )
				 	continue;
			 		
				 $term_ids[] = (int) $term->term_id;
			}
			
			wp_set_object_terms( $post->ID, $term_ids, $args['from_tax'], false );
		}	
		
		wp_set_object_terms( $post->ID, $args['to_term'], $args['to_tax'], (bool) $args['append'] );
		
	}
	
}

//Grab the form submission on page load
add_action( 'load-tools_page_change_tax', function() {

	if ( ! isset( $_POST['hmct-to-taxonomies'] ) || ! isset( $_POST['hmct-to-terms'] ) || ! isset( $_POST['hmct-from-taxonomies'] ) || ! isset( $_POST['hmct-from-terms'] ) )
		return;	
	
	if ( isset( $_POST['hmct-insertion-method'] ) )
		$append = ( $_POST['hmct-insertion-method'] == "1"  ) ? true : false;
	else
		$append = false;
		
	if ( isset( $_POST['hmct-delete-origin'] ) )
		$delete_origin = ( $_POST['hmct-delete-origin'] == "1"  ) ? true : false;
	else
		$delete_origin = false;		

	hmct_swap_them( array(  
		
		'from_tax' 		=> $_POST['hmct-from-taxonomies'],
		'to_tax' 		=> $_POST['hmct-to-taxonomies'],
		'from_term' 	=> $_POST['hmct-from-terms'],
		'to_term'		=> (int) $_POST['hmct-to-terms'],
		'append'		=> $append,
		'delete_origin' => $delete_origin
	
	) );
	
	wp_redirect( add_query_arg( array( 
		'status' => 'done',
		'taxonomy_from' => $_POST['hmct-from-taxonomies'],
		'taxonomy_to' => $_POST['hmct-to-taxonomies']
		) ), 303 );
	exit;

} );

//Ajax - Grab terms on taxonomy option change then insert them into the DOM
add_action( 'wp_ajax_hm_change_tax', function() {
	
	$tax = (string) $_POST['tax_slug'];
	$multiple = (bool) $_POST['multiple'];
	
	$terms = get_terms( $tax, 'hide_empty=1' );
	
	if ( is_wp_error( $terms ) || ! $terms )
		exit; ?>
	
	<select <?php echo $multiple ? 'multiple' : '' ?> name="hmct-<?php echo (string) $_POST['direction']; ?>-terms<?php echo $multiple ? '[]' : '' ?>" class="hmct-term-selector">
		
		<option value=""> - Term - </option>
		
		<?php foreach( $terms as $key => $term ): ?>
		
			<option value="<?php echo $term->term_id; ?>"><?php echo $term->name; ?></option>	
		
		<?php endforeach; ?>
	
	</select>
	
	<?php exit;

} );

//Load scripts and styles required for this plugin
add_action( 'load-tools_page_change_tax', function () { 
		
	add_action( 'admin_head', function() {
	?>
		<style>
		
			.hmct { margin-top: 20px; }
			.hmct-form { padding: 20px; }
			.hmct select { margin: 10px; }
			.hmct input[type="checkbox"] { margin-right: 20px; }
			.hmct-no-term-prompt { padding: 10px; }
		
		</style>
		
		<script type="text/javascript">
		
			jQuery(document).ready(function($) { 
			
				jQuery( '.hmct' ).on( 'change', '.hmct-tax-selector', function ( e ) {
						
						jQuery( this ).closest( '.hmct-tax-container' ).find( '.hmct-term-selector' ).remove();	
						jQuery( this ).closest( '.hmct-tax-container' ).find( '.hmct-no-term-prompt' ).remove();
						
						var selector = jQuery( this )
						var direction = jQuery( this ).attr( 'data-direction' );
						
						if ( jQuery( this ).val().length ) {
						
							jQuery.post( ajaxurl, { action: 'hm_change_tax', tax_slug: jQuery( this ).val(), direction: direction, multiple: jQuery( this ).attr( 'data-multiple-terms' ) }, function( data ) { 
								
								if ( data.length ) 
									selector.closest( '.hmct-tax-container' ).append( data );
								
								else
									selector.closest( '.hmct-tax-container' ).append( '<span class="hmct-no-term-prompt">This Tax has no Terms</span>' );
							} );					
						}
				} );

				//fire on load
				jQuery( '.hmct .hmct-tax-selector' ).change();
				
			} );

		</script>
		
		<?php
	} );	
	
} ); 

