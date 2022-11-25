require("dotenv").config();

let formattedOutput = '{ "wc_version": "' + process.env.WC_VERSION + '", "plugin_name": "' + process.env.PLUGIN_NAME + '" }'

console.log(formattedOutput)