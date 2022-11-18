const  data  = require('./output.json');
// import { data } from './output.json'

let newData = JSON.stringify(data)

let newRow = newData.split(',')

// let xString = '{ "test1":"' + newData + '"}'

console.log("test1:" + newRow)

