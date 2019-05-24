<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class UrlFrontier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'url:frontier';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetching a single url';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->client = new Client;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->url = $url = "www.carnet.hr";
        $response  = $this->client->get($url);
        $body      = (string) $response->getBody();

        if (!file_exists(storage_path($url))) {
            mkdir(storage_path($url));
        }

        file_put_contents(storage_path($url)."/index", $body);

        $links = $this->extractLinks($body);

        $externalLinks = $this->getExternalLinks($links);
        $internalLinks = $this->getInternalLinks($links);

        dd($externalLinks);
    }

    public function extractLinks($html)
    {
        $dom = new \DOMDocument;

        @$dom->loadHTML($html);
        $links = $dom->getElementsByTagName('a');

        $linkArray = [];

        foreach ($links as $link) {
            array_push($linkArray, $link->getAttribute('href'));
        }
        return array_unique($linkArray);
    }

    public function getInternalLinks($links)
    {
        $internalLinks = [];

        foreach ($links as $link) {
            $parsedLink = parse_url($link);
            if (isset($parsedLink['host']) && $parsedLink['host'] != $this->url) {
                //those are external links
            } else {
                if (isset($parsedLink['host']) && isset($parsedLink['path']) && $parsedLink['path'] != "/") {
                    $internalLinks[] = $parsedLink['host'].$parsedLink['path'];
                }
                if (substr($link, 0, 1) === "/" && $link != "/") {
                    $internalLinks[] = $this->url.$link;
                }
            }
        }
        return array_unique($internalLinks);
    }

    public function getExternalLinks($links)
    {
        $externalLinks = [];

        foreach ($links as $link) {
            $parsedLink = parse_url($link);
            if (isset($parsedLink['host']) && $parsedLink['host'] != $this->url) {
                $externalLinks[$parsedLink['host']] = $parsedLink['host'];
            }
        }
        return array_unique(array_values($externalLinks));
    }
}
