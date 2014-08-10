<?php
namespace Baobab;
/**!
 * .. class:: BaobabNode($id,$lft,$rgt,$parentId[,$fields=NULL])
 *
 *    Node of a Baobab tree
 *
 *    :param $id: the node id
 *    :type $id: int
 *    :param $lft: the node left bound
 *    :type $lft: int
 *    :param $rgt: the node right bound
 *    :type $rgt: int
 *    :param $parentId: the parent's node id, if any
 *    :type $parentId: int or NULL
 *    :param $fields_values: additional fields of the node (mapping fieldName => value)
 *    :type $fields_values: array or NULL
 *
 *    **Attributes**:
 *       **id** int, node id
 *
 *       **lft**  int, left value
 *
 *       **rgt**  int, right value
 *
 *       **parentNode**  int, the parent node id
 *
 *       **fields_values**  array, additional fields of the node
 *
 *       **children** array, instances of BaobabNode children of the current node
 *
 *    .. note::
 *       this class doesn't have database interaction, its purpose is
 *       just to have a runtime representation of a Baobab tree. The data
 *       inserted is supposed to be valid in his tree (e.g. $this->lft cant'
 *       be -1 or major of any node right value)
 *
 */
class BaobabNode
{
    public $id;
    public $lft;
    public $rgt;
    public $parentNode;
    public $fields_values;
    public $children;

    public function __construct($id,$lft,$rgt,$parentNode,&$fields_values=NULL)
    {
        $this->id = $id;
        $this->lft = $lft;
        $this->rgt = $rgt;
        $this->parentNode = $parentNode;
        $this->fields_values = $fields_values;

        $this->children = array();
    }

    /**!
     * .. method:: appendChild($child)
     *
     *    Add a node as last sibling of the node's children.
     *
     *    :param $child: append a node to the list of this node children
     *    :type $child: :class:`BaobabNode`
     *
     */
    public function appendChild($child)
    {
        $this->children[] = $child;
    }

    /**!
     * .. method:: stringify([$fields=NULL[,$diveInto=TRUE[,$indentChar=" "[,$indentLevel=0]]]])
     *
     *    Return a representation of the tree as a string.
     *
     *    :param $fields: what node fields include in the output. id, lft and rgt
     *                      are always included.
     *    :type $fields:  array
     *    :param $diveInto: wheter to continue with node's children or not
     *    :type $diveInto:  boolean
     *    :param $indentChar: character to use to indent
     *    :type $indentChar:  string
     *    :param $indentLevel: how deep we are indenting
     *    :type $indentLevel:  int
     *
     *    :return: tree or node representation
     *    :rtype:  string
     *
     *    .. note::
     *       $indentLevel is meant for internal use only.
     *
     *    .. todo::
     *       $fields is currently unused
     */
    public function stringify($fields=NULL,$diveInto=TRUE,$indentChar=" ",$indentLevel=0)
    {
        // XXX TODO $fields is not used at present (and remove the notice from documentation)
        $out = str_repeat($indentChar, $indentLevel*4)."({$this->id}) [{$this->lft}, {$this->rgt}]";
        if (!$diveInto) return $out;
        foreach ($this->children as $child) {
            $out .= "\n".$child->stringify($fields, TRUE, $indentChar, $indentLevel+1);
        }

        return $out;
    }

    /**!
     * .. method:: isRightmost()
     *
     *    Check if the node is rightmost between his siblings.
     *
     *    :return: whether if the node is the rightmost or not
     *    :rtype:  boolean
     *
     *    .. note:
     *       root node is considered to be rightmost
     */
    public function isRightmost()
    {
        if (!$this->parentNode) return TRUE;
        return $this->parentNode->children[count($this->parentNode->children)-1]->id === $this->id;
    }

    /**!
     * .. method:: isLeftmost()
     *
     *    Check if the node is leftmost between his siblings.
     *
     *    :return: whether if the node is the leftmost or not
     *    :rtype:  boolean
     *
     *    .. note:
     *       root node is considered to be leftmost
     */
    public function isLeftmost()
    {
        if (!$this->parentNode) return TRUE;
        return $this->parentNode->children[0]->id === $this->id;
    }
}