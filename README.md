# Papertrail Logging API for WordPress

## Setup

1. Get your destination from your account at https://papertrailapp.com/account/destinations
2. Install as a plugin or load it up in mu-plugins
3. Define your constant in wp-config.php `WP_PAPERTRAIL_DESTINATION`

## Make JSON pretty

You can use this Greasemonkey script to make the JSON logging look much better (and works with Tampermonkey with a small tweak, see comments).

https://gist.github.com/troy/55442ad0d2502f9ac0a7

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

### Error Logging

You can log PHP errors to Papertrail too, using this easy to set constant:

`define( 'WP_PAPERTRAIL_ERROR_HANDLER', true );`

Please be aware, some codebases produce a large amount of notices, warnings, or other messages even when they aren't displayed on the screen. Be careful with this handler enabled and watch your Papertrail plan as you might approach your limit quickly.

## Props

Props have to go to Troy Davis (@troy on GitHub, @troyd on Twitter) who came up with the PHP interface to communicate with the Papertrail API.

See the original gist code here: https://gist.github.com/troy/2220679

I also referenced the Stream to Papertrail plugin (https://github.com/Japh/stream-to-papertrail) initially.
