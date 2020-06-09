<?php

namespace Papyrus\Content;

use Countable;
use Iterator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;

class Pagination implements Iterator, Countable
{
    protected $all;
    protected $items;
    protected $offset;
    protected $limit;
    protected $page;

    public function __construct($items, $limit, int $page = null)
    {
        if (is_null($page)) {
            parse_str(current_url(), $query);
            $page = intval($query['page'] ?? 1);
        }

        $this->all = collect($items);
        $this->page = $page;
        $this->limit = $limit;
        
        $this->refresh();
    }

    protected function refresh(): Pagination
    {
        $this->offset = ($this->page - 1) * $this->limit;
        $this->items = $this->all->slice($this->offset, $this->limit);

        return clone $this;
    }

    public function url($current): string
    {
        $url = parse_url($current);
        parse_str($url['query'] ?? '', $query);

        $query['page'] = $this->page;
        $url['query'] = Request::normalizeQueryString(http_build_query($query, '', '&'));

        return build_url($url);
    }

    public function find($callback): ?Pagination
    {
        foreach ($this as $page) {
            if ($callback($page)) {
                return $page;
            }
        }

        return null;
    }

    public function items(): Collection
    {
        return $this->items;
    }

    public function index(): int
    {
        return $this->page;
    }

    public function getPage($index): ?Pagination
    {
        if ($this->hasPage($index)) {
            return new static($this->all, $this->limit, $index);
        }

        return null;
    }

    public function hasPage($index): bool
    {
        return ($index >= 1 && $index <= $this->count());
    }

    public function count(): int
    {
        return floor($this->all->count() / $this->limit);
    }

    public function hasPrevious(): bool
    {   
        return $this->hasPage($this->page - 1);
    }

    public function getPrevious(): ?Pagination
    {
        return ($this->hasPage($this->page - 1)) ? new static($this->all, $this->limit, $this->page - 1) : null;
    }

    public function hasNext(): bool
    {
        return $this->hasPage($this->page + 1);
    }

    public function getNext(): ?Pagination
    {
        return ($this->hasPage($this->page + 1)) ? new static($this->all, $this->limit, $this->page + 1) : null;
    }

    public function getFirst(): Pagination
    {
        return new static($this->all, $this->limit, 1);
    }

    public function getLast(): Pagination
    {
        return new static($this->all, $this->limit, $this->count());
    }

    // Iterator
    public function rewind(): void
    {
        $this->page = 1;

        $this->refresh();
    }

    public function current(): Pagination
    {
        return $this;
    }

    public function key(): int
    {
        return $this->page;
    }

    public function next(): void
    {
        ++$this->page;

        $this->refresh();
    }

    public function valid(): bool
    {
        return $this->hasPage($this->page);
    }
}