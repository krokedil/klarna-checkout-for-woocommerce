require("dotenv").config();

let formattedOutput = '{ "wc_version": "' + process.env.WC_VERSION + '", "wp_version": "' + process.env.WP_VERSION + '", "plugin_name": "' + process.env.PLUGIN_NAME + '" }'

console.log(formattedOutput)