<?php

namespace Isotop\Imgix;

class Imgix {

	/**
	 * Class instance.
	 *
	 * @var \Isotop\Imgix\Imgix
	 */
	protected static $instance;

	/**
	 * Get class instance.
	 *
	 * @return \Isotop\Imgix\Imgix
	 */
	public static function instance() {
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static;
		}

		return static::$instance;
	}

	/**
	 * Imgix constructor.
	 */
	public function __construct() {
		// Disable imgix if `IMGIX_DISABLED` is defined.
		if ( defined( 'IMGIX_DISABLED' ) ) {
			add_filter( 'pre_option_imgix_settings', '__return_empty_array' );
		}

		if ( ! class_exists( '\Images_Via_Imgix' ) ) {
			return;
		}

		// Override imgix options if `IMGIX_HELPER_OVERRIDE` is defined.
		if ( defined( 'IMGIX_HELPER_OVERRIDE' ) && IMGIX_HELPER_OVERRIDE ) {
			$options = get_option( 'imgix_settings', [] );

			if ( ! is_array( $options ) ) {
				$options = [
					'cdn_link'     => '',
					'auto_format'  => 1,
					'auto_enhance' => 1
				];
			}

			if ( ! empty( $options['cdn_link'] ) && defined( 'IMGIX_HELPER_CDN_LINK' ) ) {
				$options['cdn_link'] = IMGIX_HELPER_CDN_LINK;
			}

			\Images_Via_Imgix::instance()->set_options( $options );
		}

		// Disable thumbnail creation.
		if ( defined( 'IMGIX_HELPER_DISABLE_THUMBNAIL' ) && IMGIX_HELPER_DISABLE_THUMBNAIL ) {
			add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
		}

		// Add imgix thumbnails.
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'add_imgix_sizes' ], 10, 2 );

		// Add retina.
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'add_retina' ], 10, 3 );

		// Allow srcset attribute.
		add_filter( 'wp_kses_allowed_html', [ $this, 'allow_srcset' ] );

		// Fix gif urls.
		add_filter( 'wp_get_attachment_url', [ $this, 'fix_gif' ], 99 );
	}

	/**
	 * Generate image size meta without generating files.
	 *
	 * @param  array $metadata
	 * @param  int   $attachment_id
	 *
	 * @return array
	 */
	public function add_imgix_sizes( $metadata, $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( wp_attachment_is_image( $attachment ) ) {
			$file = get_attached_file( $attachment_id, true );

			foreach ( $this->get_all_defined_sizes() as $size_name => $size ) {
				$metadata['sizes'][ $size_name ] = [
					'file'      => $this->generate_imgix_path( basename( $file ), $size ),
					'width'     => $size['width'],
					'height'    => $size['height'],
					'crop'      => $size['crop'],
					'mime-type' => get_post_mime_type( $attachment ),
				];
			}
		}

		return $metadata;
	}

	/**
	 * Add retina to attachment image tags.
	 *
	 * @param  array        $attr
	 * @param  \WP_Post     $attachment
	 * @param  array|string $size
	 *
	 * @return array
	 */
	public function add_retina( $attr, $attachment, $size ) {
		$size_params   = $this->get_size_params( $size, $attachment );
		$retina_params = $this->get_size_params( $size, $attachment, 2 );

		if ( ! empty( $size_params ) ) {
			$image_url = wp_get_attachment_url( $attachment->ID );

			$srcset = [];
			if ( ! empty( $attr['srcset'] ) ) {
				foreach ( explode( ',', $attr['srcset'] ) as $size_set ) {
					list( $url, $limit )      = explode( ' ', trim( $size_set ) );
					$srcset[ trim( $limit ) ] = trim( $url );
				}
			}

			$srcset[ $size_params['w'] . 'w' ]   = add_query_arg( $size_params, $image_url ) . ' ' . $size_params['w'] . 'w';
			$srcset[ $retina_params['w'] . 'w' ] = add_query_arg( $retina_params, $image_url ) . ' ' . $retina_params['w'] . 'w';

			krsort( $srcset );

			$attr['srcset'] = implode( ', ', $srcset );

			$attr['srcset'] = implode( ', ', $srcset );

			$attr['sizes'] = $size_params['w'] . 'px';
		}

		return $attr;
	}

	/**
	 * Add srcset and sizes as allowed attributes in img tag.
	 *
	 * @param  array $allowed_tags
	 *
	 * @return array
	 */
	public function allow_srcset( $allowed_tags ) {
		if ( isset( $allowed_tags['img'] ) ) {
			$allowed_tags['img']['srcset'] = true;
			$allowed_tags['img']['sizes']  = true;
		}

		return $allowed_tags;
	}

	/**
	 * Remove auto compress from gif images.
	 *
	 * @param  string $url
	 *
	 * @return string
	 */
	public function fix_gif( $url ) {
		$parsed_url = wp_parse_url( $url );
		$ext        = pathinfo( $parsed_url['path'], PATHINFO_EXTENSION );

		if ( $ext === 'gif' && ! empty( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $query );
			if ( ! empty( $query['auto'] ) ) {
				$auto          = explode( ',', $query['auto'] );
				$auto          = array_diff( $auto, [ 'compress' ] );
				$query['auto'] = implode( ',', $auto );

				$url = remove_query_arg( 'auto', $url );
				$url = add_query_arg( 'auto', $query['auto'], $url );
			}
		}

		return $url;
	}

	/**
	 * Generate imgix path.
	 *
	 * @param  string $filename
	 * @param  array  $size
	 *
	 * @return string
	 */
	protected function generate_imgix_path( $filename, $size ) {
		$params = [
			'w' => $size['width'],
			'h' => $size['height'],
		];

		$params = array_filter( $params );

		if ( ! empty( $size['crop'] ) ) {
			$params['fit'] = 'crop';
		}

		return $filename . '?' . build_query( $params );
	}

	/**
	 * Generate size array from image size.
	 *
	 * @param  array|string $size
	 * @param  \WP_Post     $attachment
	 * @param  int          $multiply
	 *
	 * @return array
	 */
	protected function get_size_params( $size, $attachment, $multiply = 1 ) {
		$params = [];
		if ( is_array( $size ) ) {
			$params = [
				'w' => $size[0] ?? 0,
				'h' => $size[1] ?? 0,
			];
		} else {
			$sizes = $this->get_all_defined_sizes();
			if ( isset( $sizes[ $size ] ) ) {
				$params = [
					'w' => $sizes[ $size ]['width'] ?? 0,
					'h' => $sizes[ $size ]['height'] ?? 0,
				];
			}
		}
		$params = array_filter( $params );

		if ( empty( $params['w'] ) ) {
			$image_meta  = wp_get_attachment_metadata( $attachment->ID );
			$params['w'] = round( $image_meta['width'] * $params['h'] / $image_meta['height'] );
		}

		$params = array_map( function ( $el ) use ( $multiply ) {
			return $el * $multiply;
		}, $params );

		return $params;
	}

	/**
	 * Get all defined image sizes.
	 *
	 * @return array
	 */
	protected function get_all_defined_sizes() {
		// Make thumbnails and other intermediate sizes.
		$theme_image_sizes = wp_get_additional_image_sizes();

		$sizes = [];
		foreach ( get_intermediate_image_sizes() as $s ) {
			$sizes[ $s ] = [
				'width'  => '',
				'height' => '',
				'crop'   => false,
			];
			if ( isset( $theme_image_sizes[ $s ]['width'] ) ) {
				// For theme-added sizes
				$sizes[ $s ]['width'] = intval( $theme_image_sizes[ $s ]['width'] );
			} else {
				// For default sizes set in options
				$sizes[ $s ]['width'] = get_option( "{$s}_size_w" );
			}

			if ( isset( $theme_image_sizes[ $s ]['height'] ) ) {
				// For theme-added sizes
				$sizes[ $s ]['height'] = intval( $theme_image_sizes[ $s ]['height'] );
			} else {
				// For default sizes set in options
				$sizes[ $s ]['height'] = get_option( "{$s}_size_h" );
			}

			if ( isset( $theme_image_sizes[ $s ]['crop'] ) ) {
				// For theme-added sizes
				$sizes[ $s ]['crop'] = $theme_image_sizes[ $s ]['crop'];
			} else {
				// For default sizes set in options
				$sizes[ $s ]['crop'] = get_option( "{$s}_crop" );
			}
		}

		return $sizes;
	}
}
