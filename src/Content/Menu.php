<?php namespace Papyrus\Content;

use Iterator;
use ArrayAccess;
use Soma\Manifest;
use Illuminate\Support\Str;
use Papyrus\Content\Filesystem;

class Menu implements Iterator, ArrayAccess
{
    protected $items = [];
    protected $position = 0;
    protected $files;

    public function __construct($definition, Filesystem $files)
    { 
        $this->files = $files;

        // If definition is path we load from manifest
        if (is_string($definition) && file_exists($definition)) {
            $definition = (new Manifest($definition, true, true))->all();
        }
        if (! is_array($definition)) {
            throw new InvalidArgumentException("Definition must be either a path to a manifest or an already parsed manifest");
        }

        // Parse the menu definition
        foreach ($definition as $key => $item) {
            $this->items[$key] = $this->processMenuItem($item);
        }
    }

    public function hasItems()
    {
        return ! empty($this->items);
    }

    public function items()
    {
        return $this->items;
    }

    protected function processMenuItem($item)
    {
        // Set page and url
        if (isset($item['page'])) {
            if ($page = $this->files->get($item['page'])) {
                $item['url'] = $page->url;
                $item['route'] = $page->route;
                $item['label'] = $item['label'] ?? $page->title;
            }

            unset($item['page']);
        }
        // Use list to lookup children
        elseif (isset($item['list'])) {
            if ($page = $this->files->get($item['list'])) {
                $item['url'] = $page->url;
                $item['route'] = $page->route;
                $item['label'] = $item['label'] ?? $page->title;
            }
            
            $sort = $item['sort'] ?? false;
            $order = strtolower($item['order'] ?? 'asc');
            $depth = $item['depth'] ?? 1;
            $flat = $item['flat'] ?? false;
            $flat = $flat ?: ($depth == 1);
            $routes = $this->files->query($item['list'], 0); 

            // ignore creating the hierarchy
            if ($flat) {
                $item['children'] = [];

                foreach ($routes as $page) {
                    $child = [];
                    $child['url'] = $page->url;
                    $child['route'] = $page->route;
                    $child['label'] = $page->title;
                    $item['children'][] = $child;
                }

                // Sort level
                $item['children'] = $this->sortChildren($item['children'], $sort, $order);
            }
            // create route hierarchy
            else {
                $item['children'] = $this->processRoutesHierarchy($routes, $sort, $order);
            }

            // Limit the children to a set amount
            if (isset($item['limit'])) {
                $item['children'] = array_slice($item['children'], 0, $item['limit']);
            }
        
            unset($item['list']);
        }
        // Recursively process children
        elseif (isset($item['children'])) {
            foreach ($item['children'] as $key => $menu) {
                $item['children'][$key] = $this->processMenuItem($menu);
            }
        }

        return $item;
    }

    protected function sortChildren($children, $sort, $order)
    {
        return collect($children)->sortBy($sort, SORT_REGULAR, Str::startsWith($order, 'desc'))->values()->all();
    }

    protected function processRoutesHierarchy($routes, $sort = false, $order = 'asc')
    {
        // Remove common parent directories to avoid empty levels
        $uris = array_keys($routes);
        $common = common_path($uris);

        foreach ($routes as $uri => $page) {
            $routes[substr($uri, strlen($common))] = $page;
            unset($routes[$uri]);
        }

        // Create tree
        $hierarchy = [];
        $tree = $this->explodeTree($routes, '/');

        foreach ($tree as $dir => $node) {
            $hierarchy[] = $this->processRoutesTreeNode($node, $sort, $order);
        }

        return $hierarchy;
    }

    /**
     * Explode any single-dimensional array into a full blown tree structure,
     * based on the delimiters found in it's keys.
     *
     * @author	Kevin van Zonneveld <kevin@vanzonneveld.net>
     * @author	Lachlan Donald
     * @author	Takkie
     * @copyright 2008 Kevin van Zonneveld (https://kevin.vanzonneveld.net)
     * @license   https://www.opensource.org/licenses/bsd-license.php New BSD Licence
     * @version   SVN: Release: $Id: explodeTree.inc.php 89 2008-09-05 20:52:48Z kevin $
     * @link	  https://kevin.vanzonneveld.net/
     *
     * @param array   $array
     * @param string  $delimiter
     * @param boolean $baseval
     *
     * @return array
     */
    protected function explodeTree(array $array, $delimiter = '_', $baseval = false)
    {
        $splitRE = '/'.preg_quote($delimiter, '/').'/';
        $returnArr = [];

        foreach ($array as $key => $val) {
            // Get parent parts and the current leaf
            $parts = preg_split($splitRE, $key, -1, PREG_SPLIT_NO_EMPTY);
            $leafPart = array_pop($parts);

            // Build parent structure
            // Might be slow for really deep and large structures
            $parentArr = &$returnArr;

            foreach ($parts as $part) {
                if (! isset($parentArr[$part])) {
                    $parentArr[$part] = [];
                }
                elseif (! is_array($parentArr[$part])) {
                    if ($baseval) {
                        $parentArr[$part] = ['__base_val' => $parentArr[$part]];
                    }else {
                        $parentArr[$part] = [];
                    }
                }

                $parentArr = &$parentArr[$part];
            }

            // Add the final part to the structure
            if (empty($parentArr[$leafPart])) {
                $parentArr[$leafPart] = $val;
            }
            elseif ($baseval && is_array($parentArr[$leafPart])) {
                $parentArr[$leafPart]['__base_val'] = $val;
            }
        }

        return $returnArr;
    }

    protected function processRoutesTreeNode($node, $sort = false, $order = 'asc')
    {
        // Branch
        if (is_array($node)) {
            $item = [];

            if (isset($node['index'])) {
                $item['url'] = $node['index']->url;
                $item['route'] = $node['index']->route;
                $item['label'] = $node['index']->title;
                unset($node['index']);
            }

            if (! empty($node)) {
                $item['children'] = [];

                foreach ($node as $key => $val) {
                    $item['children'][] = $this->processRoutesTreeNode($val, $sort, $order);
                }

                // Sort level
                $item['children'] = $this->sortChildren($item['children'], $sort, $order);
            }
        }
        // Leaf
        else {
            $item = [];
            $item['url'] = $node->url;
            $item['route'] = $node->route;
            $item['label'] = $node->title;
        }

        return $item;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        return $this->items[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function valid()
    {
        return isset($this->items[$this->position]);
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->items[$offset]) ? $this->items[$offset] : null;
    }
}