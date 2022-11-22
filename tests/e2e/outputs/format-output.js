require("dotenv").config();

// const { execSync } = require("child_process");

// const { SITEHOST, PORT, PLUGIN_NAME, KOM } = process.env;
// const  data  = require('./output.json');
// // import { data } from './output.json'

// let newData = JSON.stringify(data)

// execSync(`php -version`, {
//     stdio: "inherit",
// });

let xString = '{ "wc_version": "' + process.env.WC_VERSION + '", "wp_version": "' + process.env.WP_VERSION + '", "plugin_name": "' + process.env.PLUGIN_NAME + '" '

// let xString = process.env.WC_VERSION
// let xString = process.env.LOGNAME

console.log(xString)

