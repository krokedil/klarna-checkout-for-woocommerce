require("dotenv").config();
const { SITEHOST, PORT, PLUGIN_NAME, KOM } = process.env;
const  data  = require('./output.json');
// import { data } from './output.json'

let newData = JSON.stringify(data)

let newRow = newData.split(',')
let x = []

newRow.forEach(element => {
    element = element.replace(/\"/g, '')
    if(element[0] == "{") {
        element = element.replace(element[0], '')
    }

    x.push(element)
});

x = x.toString()
x = x.replace(/.$/, '')

let xString = '{ "test1": "' + x + '"}'

console.log(process.env)

