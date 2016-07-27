<?php
$page_links = paginate_links( array(
	'base'      => add_query_arg( 'page_num', '%#%' ),
	'format'    => '',
	'prev_text' => __( '&laquo;', 'text-domain' ),
	'next_text' => __( '&raquo;', 'text-domain' ),
	'total'     => $num_of_pages,
	'current'   => $page_num,
) );
?>
<div class="tablenav">
	<div class="tablenav-pages shareasale-tracker-logs-pagination">
		<?php echo $page_links; ?>
	</div>
</div>
