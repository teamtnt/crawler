<?php

namespace App\Console\Commands;

use App\DomainFeeder;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Log;

class UrlFrontier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'url:frontier {url}';

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
        $this->url = $url = $this->argument('url');

        Log::info("Searching the url now: ".$url);
        sleep(15);
        return;

        $response = $this->client->get($url);
        $body     = (string) $response->getBody();

        /*
        if (!file_exists(storage_path("domains/".$url))) {
        mkdir(storage_path("domains/".$url));
        }

        file_put_contents(storage_path("domains/".$url)."/index", $body);
         */
        $links = $this->extractLinks($body);

        $externalLinks = $this->getExternalLinks($links);
        $internalLinks = $this->getInternalLinks($links);

        $externalLinksCount = count($externalLinks);
        $internalLinksCount = count($internalLinks);

        $this->info("Found {$externalLinksCount} internal links and {$internalLinksCount} external links");

        $this->saveExternalLinksToDomainFeeder($externalLinks);
    }

    public function saveExternalLinksToDomainFeeder($domains)
    {
        $count = count($domains);
        $this->warn("Saving $count domains to Domain Feeder");
        foreach ($domains as $domain) {
            try {
                DomainFeeder::insert(["domain" => $domain]);
            } catch (Exception $e) {
                //$this->error($e->getMessage());
                //the domain already exists in the feeder
            }
        }
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
                $domain                 = $parsedLink['host'];
                $externalLinks[$domain] = $domain;
            }
        }
        return array_unique(array_values($externalLinks));
    }
}
