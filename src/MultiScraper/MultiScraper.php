<?php

namespace YeTii\MultiScraper;

/**
 * Class MultiScraper
 */
class MultiScraper
{
    /**
     * @var bool
     */
    private $debug = true;
    /**
     * @var array
     */
    protected $sites = [];
    /**
     * @var null
     */
    protected $user = null;
    /**
     * @var null
     */
    protected $query = null;
    /**
     * @var int
     */
    protected $page = 1;
    /**
     * @var array
     */
    protected $torrents = [];
    /**
     * @var array
     */
    protected $require_fields = [];
    /**
     * @var bool
     */
    protected $require_all = false;

    /**
     * MultiScraper constructor.
     *
     * @param array|null $args
     */
    public function __construct(array $args = null)
    {
        $this->sites = require __DIR__ . '/scrapers.php';
    }

    /**
     * Get an attribute
     *
     * @param string $name
     * @return null|mixed
     */
    public function __get(string $name)
    {
        return isset($this->{$name}) ? $this->{$name} : null;
    }

    /**
     * Set an attribute
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set(string $name, $value)
    {
        $this->{$name} = $value;
    }

    /**
     * Get the latest torrents from a site
     *
     * @param int $page
     * @return array
     */
    public function latest($page = 1)
    {
        $this->page = $page;

        return $this->scrape();
    }

    /**
     * Get torrents that match a search query
     *
     * @param string $query
     * @param int    $page
     * @return array
     */
    public function search($query, $page = 1)
    {
        $this->query = $query;
        $this->page = $page;

        return $this->scrape();
    }

    /**
     * Get torrents that match a username
     *
     * @param string $user
     * @param int    $page
     * @return array
     */
    public function user($user, $page = 1)
    {
        $this->user = $user;
        $this->page = $page;

        return $this->scrape();
    }

    /**
     * Scrape a page for torrent data
     *
     * @return array|bool
     */
    public function scrape()
    {
        $all = [];
        if (!$this->sites) {
            return false;
        }
        foreach ($this->sites as $site) {
            $torrents = null;
            if ($this->user) {
                $torrents = $site->scrapeUser($this->user, $this->page);
            } elseif ($this->query) {
                $torrents = $site->scrapeSearch($this->query, $this->page);
            } else {
                $torrents = $site->scrapeLatest($this->page);
            }
            if ($torrents) {
                $all = array_merge($all, $torrents);
            }
        }

        $all = $this->only_valid_torrents($all);

        return $all;
    }

    /**
     * Require certain fields in order to successfully return torrent
     *
     * @throws \Exception
     */
    public function require_fields()
    {
        $args = func_get_args();
        foreach ($args as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $ar) {
                    $this->require_field($ar);
                }
            } else {
                $this->require_field($arg);
            }
        }
    }

    /**
     * Require all fields in order to successfully return torrent
     *
     */
    public function require_all()
    {
        $this->require_fields = (new Site())->available_attributes;
    }

    /**
     * Add a required field a torrent must have
     *
     * @param string $name
     * @throws \Exception
     */
    public function require_field($name)
    {
        if (!is_string($name)) {
            throw new \Exception("Invalid field (non-string)", 1);
        }
        $available = (new Site())->available_attributes;
        if (!in_array($name, $available)) {
            throw new \Exception("Unknown field name: `{$name}`", 1);
        }

        if (!in_array($name, $this->require_fields)) {
            $this->require_fields[] = $name;
        }
    }

    /**
     * Check if all required fields are present in an array of torrents
     *
     * @param array $torrents
     * @return array
     */
    public function only_valid_torrents(array $torrents)
    {
        if (empty($this->require_fields)) {
            return $torrents;
        }
        $valid = [];
        foreach ($torrents as $torrent) {
            $is_valid = true;
            foreach ($this->require_fields as $field) {
                if (!isset($torrent->{$field})) {
                    $is_valid = false;
                    // printDie("Failing {$torrent->hash} - Does not meet criteria (missing: `$field`)", false);
                }
            }
            if ($is_valid) {
                $valid[] = $torrent;
                // printDie("Keeping {$torrent->hash} - Meets all criteria :)", false);
            }
        }

        return $valid;
    }

}