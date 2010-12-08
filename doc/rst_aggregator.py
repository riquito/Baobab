#!/usr/bin/env python

import os, re,sys

text=file("../src/baobab.php").read()
#find the comment blocks
comment_blocks=re.findall("([\t ]*)/\*\*!(.*?)\*/",text,re.S)

for part in comment_blocks:
    len_indent=len(part[0])
    indent=''
    
    if len_indent:
        indent=' '*int(round(len_indent/3)*3) # we want indent to be always multiple of 3
    block=part[1]
    
    # from each line in a block remove the initial "<spaces>*<singleSpace>"
    for i in re.findall("\* ?(.*)$",block,re.M):
        sys.stdout.write(indent)
        print i
    print