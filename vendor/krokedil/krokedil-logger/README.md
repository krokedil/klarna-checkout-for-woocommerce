# krokedil-logger
## Installation
Install with [Composer](getcomposer.org).

Add these lines to your composer.json:
```
{
    "require": {
        "krokedil/krokedil-logger": "^1.0"
    }
}
```

## Usage
### Log event
Use the function **krokedil_log_events**. 
```
Example: krokedil_log_events( $order_id, $title, $data );
```
$order_id = The WooCommerce order id. Can be sent as null if you want to log events before an order exists.

$title = The title that you wish to have for the event.

$data = An **array** of the data that you want to log.

### Set the version used for order

Use the function **krokedil_set_order_gateway_version**. 
```
Example: krokedil_set_order_gateway_version( $order_id, $version );
```
$order_id = The WooCommerce order id.

$version = The version that you want to log for the order.

Use this function at a point where an order exists, for example thank you page or process_order.

### Set display on/off
To switch between showing and not showing the logs on the order add a define for **KROKEDIL_LOGGER_ON** to turn it on.
```
Example: define( 'KROKEDIL_LOGGER_ON', true );
```

### Set gateway filter
You need to set what gateway the meta box should be allowed for. Do this using the define **KROKEDIL_LOGGER_GATEWAY**.
```
Example: define( 'KROKEDIL_LOGGER_GATEWAY', '$string' );
```
$string = A string or substring of the gateway id.

### Recognition
This plugin uses the renderjson JavaScript created by GitHub user [Caldwell](https://github.com/caldwell/). It can be found here: [RenderJSON](https://github.com/caldwell/renderjson).