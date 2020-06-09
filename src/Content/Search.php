<?php namespace Papyrus\Content;

use CachingIterator;
use IteratorAggregate;
use Traversable;
use ArrayAccess;
use Countable;
use Papyrus\Content\Page;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class Search implements Countable, ArrayAccess, IteratorAggregate
{
    protected $position = 0;

    protected $results;
    protected $terms;
    protected $excludes = [];
    protected $low_value = [];

    protected $rank = [];

    public function __construct($pages, string $terms, array $excludes = [], array $low_value = [])
    {
        if (empty($terms)) {
            return collect();
        }

        $this->results = collect($pages);
        $this->terms = $terms;
        $this->excludes = $excludes;
        $this->low_value = $low_value;

        // Remove excluded files/directories
        if (! empty($this->excludes)) {
            foreach ($this->excludes as $path) {
                $path = ltrim($path, '/');

                foreach ($this->results as $key => $page) {
                    if (Str::startsWith(ltrim($page->relativePath, '/'), $path)) {
                        unset($this->results[$key]);
                    }
                }
            }
        }
        
        // Calculate rank and remove all with 0
        $this->results->map(fn($page) => $this->getSearchRank($page));

        $this->results = $this->results->filter(function($page, $key) {
            if ($this->rank[$page->id] > 0) {
                return true;
            }

            $page->unload();
            return false;
        } );

        // Sort by search_rank
        $this->results = $this->results->sort(function ($a, $b) {
            if ($this->rank[$a->id] == $this->rank[$b->id]) {
                return 0;
            }

            return $this->rank[$a->id] > $this->rank[$b->id] ? -1 : 1;
        });
    }

    public function getSummary($id, $radius = 50, $max = 10, $delimiter = ' ... ') : ?string
    {
        if ($id instanceof Page) {
            $id = $id->id;
        }
        if (! $this->results->has($id)) {
            return null;
        }

        $page = $this->results[$id];

        // lookahead/behind assertions ensures cut between terms
        $terms = join('|', preg_split('/\s+/', $this->terms));
        $s = '\s\x00-/:-@\[-`{-~'; // character set for start/end of terms
            
        preg_match_all('#(?<=['.$s.']).{1,'.$radius.'}(('.$terms.').{1,'.$radius.'})+(?=['.$s.'])#uis', $page->bare, $matches, PREG_SET_ORDER);

        // Add delimiter between occurences
        $results = [];

        foreach($matches as $line) {
            $string = htmlspecialchars($line[0], 0, 'UTF-8');
            $string = preg_replace("/[\xA0\xC2]/", " ", $string);
            $string = preg_replace('/\s+/S', " ", $string);
            
            $results[] = $string;

            if (count($results) >= $max) {
                break;
            }
        }

        $summary = implode($delimiter, $results);
        $start = substr($page->bare, 0, $radius);
        $end = substr($page->bare, -$radius);
    
        if (! Str::startsWith($summary, $start)) {
            $summary = '... '.$summary;
        }
        if (! Str::endsWith($summary, $end)) {
            $summary .= '...';
        }

        // Strip newlines
        return $summary;
    }

    public function highlightTerms(string $text, ?string $terms = null) : string
    {
        $terms = join('|', preg_split('/\s+/', $this->terms));
        $text = preg_replace('#'.$terms.'#iu', "<span class=\"highlight\">\$0</span>", $text);

        return $text;
    }

    public function count() : int
    {
        return $this->results->count();
    }

    public function terms() : string
    {
        return $this->terms;
    }

    public function results() : Collection
    {
        return $this->results;
    }

    protected function getSearchRank($page) : Page
    {
        // If there's an exact match in the title, skip a bunch of work and give it a very high score
        $escapedTerms = preg_quote($this->terms, '/');

        if (preg_match("/\b$escapedTerms\b/iu", $page->title ?? '') === 1) {
            $this->rank[$page->id] = 5;

            return $page;
        }

        // Find key terms in query
        $searchTerms = preg_split('/\s+/', $this->terms);
        $keyTerms = array_filter($searchTerms, function ($searchTerm) {
            return ! $this->isLowValueWord($searchTerm);
        });

        if (! empty($keyTerms)) {
            $searchTerms = $keyTerms;
        }

        // Calculate total score and set rank for each term
        $this->rank[$page->id] = array_sum(array_map(function ($term) use ($page) {
            // Get combined score of title and content
            $score = $this->getSearchRankForString($term, $page->title) + $this->getSearchRankForString($term, $page->bare) * 0.2;

            return $score;
        }, $searchTerms));

        return $page;
    }

    protected function getSearchRankForString($searchTerm, $content) : int
    {
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\n", " ", $content);

        $searchTermValue = $this->isLowValueWord($searchTerm) ? 0.2 : 1;
        $escapedSearchTerm = preg_quote($searchTerm, '/');

        $fullWordMatches = preg_match_all("/\b$escapedSearchTerm\b/iu", $content);
        if ($fullWordMatches > 0) {
            return min($fullWordMatches, 3) * $searchTermValue;
        }

        $startOfWordMatches = preg_match_all("/\b$escapedSearchTerm\B/iu", $content);
        if ($startOfWordMatches > 0) {
            return min($startOfWordMatches, 3) * 0.5 * $searchTermValue;
        }

        $inWordMatches = preg_match_all("/\B$escapedSearchTerm\B/iu", $content);
        return min($inWordMatches, 3) * 0.05 * $searchTermValue;
    }

    protected function isLowValueWord($searchTerm) : bool
    {
        return in_array(mb_strtolower($searchTerm), $this->low_value);
    }

    public function getIterator() : Traversable
    {
        return $this->results->getIterator();
    }

    public function getCachingIterator($flags = CachingIterator::CALL_TOSTRING) : CachingIterator
    {
        return $this->results->getCachingIterator($flags);
    }

    public function offsetSet($offset, $value)
    {
        $this->results->offsetSet($offset, $value);
    }

    public function offsetExists($offset)
    {
        return $this->results->offsetExists($offset);
    }

    public function offsetUnset($offset)
    {
        $this->results->offsetUnset($offset);
    }

    public function offsetGet($offset)
    {
        return $this->results->offsetGet($offset);
    }

    public function __call(string $method, array $args)
    {
        return call_user_func_array([$this->items, $method], $args);
    }
}