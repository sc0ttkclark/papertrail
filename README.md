# Papertrail Logging API for WordPress

## Setup

1. Get your destination from your account at https://papertrailapp.com/account/destinations
2. Install as a plugin or load it up in mu-plugins
3. Define your settings `WP_PAPERTRAIL_DESTINATION` and `WP_PAPERTRAIL_COLORIZE` (if wanted)

## Usage

```php
// Log data
$success = WP_Papertrail_API::log( $some_string_array_or_object, 'Some optional identifier' );

if ( ! is_wp_error( $success ) ) {
    // Successfully logged to Papertrail
}
```

## Options

### Destination

You will need to define the destination to log to in wp-config.php (see https://papertrailapp.com/account/destinations)

`define( 'WP_PAPERTRAIL_DESTINATION', 'logs1.papertrailapp.com:12345' );`

### Colorize JSON

If you would like to take advantage of colorization of JSON, you can enable it.

`define( 'WP_PAPERTRAIL_COLORIZE', true );`
