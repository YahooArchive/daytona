#!/usr/local/bin/node

var cluster = require('cluster');
var http = require('http');

var numCPUs = process.argv[2];
var cpuiter = process.argv[3];

function runCPU(cpu) {
    var i, j, c = 0;

    cpu = parseInt(cpu, 10);
    if (!isNaN(cpu)) {

        for (i = 0; i < cpu; i++) {
            for (j = 0; j < cpu; j++) {
                if (c % 2) {
                    c--;
                } else {
                    c++;
                }
            }
        }
    }
    return c;
}


if (cluster.isMaster) {
    for (var i = 0; i < numCPUs; i++) {
        cluster.fork();
    }
} else {
    http.createServer(function(req, res) {
         res.writeHead(200, {'Content-Type': 'text/plain'});
         runCPU(cpuiter);
         res.end('Hello World\n');

    }).listen(8080);
}
