#!/bin/bash

PDF2JSON="/home/eyob12/.nvm/versions/node/v22.15.0/bin/pdf2json"
INPUT=$1
OUTPUT=$2

$PDF2JSON -f "$INPUT" -o "$OUTPUT" -c
