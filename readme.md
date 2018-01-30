# Imigx helper

Our Imgix helper built for [https://github.com/imgix-wordpress/images-via-imgix](https://github.com/imgix-wordpress/images-via-imgix). Should be used as a mu-plugin.

## Installation

```
composer require isotopsweden/wp-imgix-helper
```

## Usage

```php
// To disable imgix:
define( 'IMGIX_DISABLED', true );

// To override settings:
define( 'IMGIX_HELPER_OVERRIDE', true );

// To change cdn link when override is defined:
define( 'IMGIX_HELPER_CDN_LINK', 'https://...' );

// Turn off disable thumbnail. Default is true.
define( 'IMGIX_HELPER_DISABLE_THUMBNAIL', false );
```

## License

MIT © Isotop
