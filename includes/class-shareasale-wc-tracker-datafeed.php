<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

class ShareASale_WC_Tracker_Datafeed {

	/**
	* @var float $version Plugin version
	* @var WP_Filesystem $filesystem WordPress filesystem object https://codex.wordpress.org/Filesystem_API
	* @var string $error_msg any failure messages
	*/
	private $version, $filesystem, $error_msg;

	public function __construct( $version, $filesystem ) {
		$this->version    = $version;
		$this->filesystem = $filesystem;

		if ( ! $this->filesystem instanceof WP_Filesystem ) {
			$this->error_msg = 'WP Filesystem API not initialized properly!';
			return false;
		}

		return $this;
	}

	public function export( $file ) {
		$product_posts  = $this->get_all_product_posts();
		$rows = array();

		foreach ( $product_posts as $product_post ) {
			//WC_Product constructor actually accepts WP post objects
			$product                  = new WC_Product( $product_post );
			$product->cross_sell_skus = $this->get_cross_sell_skus( $product );
			$rows[]                   = $this->make_row( $product );
			unset( $product );
		}

		if ( ! empty( $rows ) ) {
			$header  = implode( ',', array_keys( $rows[0] ) );
			$content = $header . "\r\n";

			foreach ( $rows as $row ) {
				$content .= implode( ',', $row ) . "\r\n";
			}
			unset( $rows );

			$csv = $this->write_file( $file, $content );
			if ( false !== $csv ) {
				$compressed = $csv->compress( $file );
				if ( false !== $compressed ) {
					$this->log();
				} else {
					//couldn't compress, so just a csv is available
					error_log( $this->get_error_msg() );
				}
			} else {
				//couldn't even create csv...
				error_log( $this->get_error_msg() );
			}
		}

		return $this;
	}

	private function get_all_product_posts() {
		$product_posts = get_posts(
			array(
				'post_type'   => array( 'product', 'product_variation' ),
				'numberposts' => -1,
				'post_status' => 'publish',
				'order'       => 'ASC',
				'orderby'     => 'ID',
			)
		);

		return $product_posts;
	}

	private function get_cross_sell_skus( $product ) {
		$cross_sell_skus = array();

		foreach ( $product->get_cross_sells() as $cross_sell_product_id ) {
			$cross_sell_skus[] = get_post_meta( $cross_sell_product_id, '_sku', true );
		}

		return $cross_sell_skus;
	}

	private function make_row( $product ) {
		$options     = get_option( 'shareasale_wc_tracker_options' );
		$merchant_id = @$options['merchant-id'];

		$row = array(
				'SKU'                                   => $product->get_sku() ? $product->get_sku() : error_log( 'no sku!' ),
				'Name'                                  => $product->get_title(),
				'URL'                                   => $product->get_permalink(),
				'Price'                                 => $product->get_sale_price(),
				'Retailprice'                           => $product->get_price(),
				'FullImage'                             => wp_get_attachment_image_src( $product->get_gallery_attachment_ids()[0], 'shop_single' )[0],
				'ThumbnailImage'                        => wp_get_attachment_image_src( $product->get_gallery_attachment_ids()[0], 'shop_thumbnail' )[0],
				'Commission'                            => '',
				'Category'                              => '',
				'Subcategory'                           => '',
				'Description'                           => $product->get_post_data()->post_content,
				'SearchTerms'                           => '',
				'Status'                                => 'instock' == $product->stock_status? 'instock' : 'soldout',
				'MerchantID'                            => empty( $merchant_id ) ? '' : $merchant_id,
				'Custom1'                               => '',
				'Custom2'                               => '',
				'Custom3'                               => '',
				'Custom4'                               => '',
				'Custom5'                               => '',
				'Manufacturer'                          => $product->get_attribute( 'manufacturer' ),
				'PartNumber'                            => $product->get_attribute( 'partnumber' ),
				'MerchantCategory'                      => '',
				'MerchantSubcategory'                   => '',
				'ShortDescription'                      => '',
				'ISBN'                                  => $product->get_attribute( 'ISBN' ),
				'UPC'                                   => $product->get_attribute( 'UPC' ),
				'CrossSell'                             => implode( ',', $product->cross_sell_skus ),
				'MerchantGroup'                         => '',
				'MerchantSubgroup'                      => '',
				'CompatibleWith'                        => '',
				'CompareTo'                             => '',
				'QuantityDiscount'                      => '',
				'Bestseller'                            => $product->is_featured() ? 1 : 0,
				'AddToCartURL'                          => $prodcut->add_to_cart_url,
				'ReviewsRSSURL'                         => '',
				'Option1'                               => '',
				'Option2'                               => '',
				'Option3'                               => '',
				'Option4'                               => '',
				'Option5'                               => '',
				'customCommissions'                     => '',
				'customCommissionIsFlatRate'            => 0,
				'customCommissionNewCustomerMultiplier' => 1,
				'mobileURL'                             => '',
				'mobileImage'                           => wp_get_attachment_image_src( $product->get_gallery_attachment_ids()[0], 'shop_single' )[0],
				'mobileThumbnail'                       => wp_get_attachment_image_src( $product->get_gallery_attachment_ids()[0], 'shop_thumbnail' )[0],
				'ReservedForFutureUse'                  => '',
				'ReservedForFutureUse'                  => '',
				'ReservedForFutureUse'                  => '',
				'ReservedForFutureUse'                  => '',
			);

		return array_map( array( $this, 'wrap_row' ), $row );
	}

	private function wrap_row( $value ) {
		$value = trim( $value );
		return '"' . str_replace( '"', '""', $value ) . '"';
	}

	private function write_file( $file, $content ) {
		if ( ! $this->filesystem->put_contents( $file, $content, FS_CHMOD_FILE ) ) {
			return false;
			$this->error_msg = $this->filesystem->errors->get_error_message();
		}

		return $this;
	}

	private function compress( $file ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->error_msg = 'Couldn\'t compress. PHP ZipArchive Class not installed or enabled.';
			return false;
		}

		$zip        = new ZipArchive;
		$compressed = $file . '.zip';

		if ( true !== $zip->open( $compressed, ZipArchive::CREATE ) ) {
			$this->error_msg = 'Couldn\'t compress because archive cannot be opened.';
			return false;
		}

		if ( ! $zip->addFile( $file, basename( $file ) ) ) {
		    $this->error_msg = 'Couldn\'t compress because CSV file not found.';
			return false;
		}

		$zip->close();
		//clean up
		$this->filesystem->delete( $file );
		return $this;
	}

	public function clean_up( $dir, $days_age = 30 ) {
		if ( ! is_numeric( $days_age ) ) {
			$days_age = 30;
		}

		$files = $this->filesystem->dirlist( $dir );

		foreach ( $files as $file_details ) {
			$filename = trailingslashit( $dir ) . $file_details['name'];
			if ( time() - $file_details['lastmodunix'] > ( 60 * 60 * 24 * $days_age ) ) {
				$this->filesystem->delete( $filename );
			}
		}
	}

	private function log() {
		return $this;
	}

	public function get_error_msg() {
		return $this->error_msg;
	}
}
