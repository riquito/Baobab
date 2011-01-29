#!/usr/bin/env python
# -*- coding: utf-8 -*-

import os
from codecs import open
from pygments import highlight
from pygments.lexers import PhpLexer
from pygments.formatters import HtmlFormatter

lexer=PhpLexer()
formatter=HtmlFormatter()

sourceExamplesDir='../src/examples/'

for fileName in os.listdir(sourceExamplesDir):
    if fileName != 'launcher.php':
        code=open(sourceExamplesDir+fileName,'rb','utf-8').read()
        highlighted=highlight(code,lexer,formatter)
        open('source/_templates/sphinxdoc/examples/'+fileName.replace('.php','.html'),'wb','utf-8'
            ).write(highlighted)
