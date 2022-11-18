const  data  = require('../config/data.json');
// import { data } from './output.json'

let newData = JSON.stringify(data)
let xString = '{ "test1":' + newData + '}'

console.log(xString)

