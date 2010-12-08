#simple sphinx extension to add json support
def setup(app):
    from sphinx.highlighting import lexers
    import pygments.lexers
    lexers['json'] =pygments.lexers.get_lexer_by_name('javascript')
