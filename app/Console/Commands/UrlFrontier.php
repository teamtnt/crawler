<?php

namespace App\Console\Commands;

use App\DomainFeeder;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Log;
use PDO;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

class UrlFrontier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'url:frontier {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetching all urls for a single domain';

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
        $this->domain = $this->argument('domain');
        $this->createUrlDatabase();

        $response = $this->client->get($this->domain);

        $body = (string) $response->getBody();

        $this->saveContentToDisk($body, $this->domain);

        $links = $this->extractLinks($body);

        $externalLinks = $this->getExternalLinks($links);
        $internalLinks = $this->getInternalLinks($links);

        $externalLinksCount = count($externalLinks);
        $internalLinksCount = count($internalLinks);

        $this->info("Found {$externalLinksCount} internal links and {$internalLinksCount} external links");

        $this->saveInternalLinksToUrlDatabase($internalLinks);
        $this->saveExternalLinksToDomainFeeder($externalLinks);
    }
    public function saveInternalLinksToUrlDatabase($links)
    {
        $statement = $this->urlDatabase->prepare("INSERT INTO urls ( 'url', 'done') values ( :url, 0)");
        foreach ($links as $link) {
            $statement->bindParam(':url', $link);
            try {
                $statement->execute();
            } catch (Exception $e) {
                //if the insert fails its a duplicate
            }
        }
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
            if (isset($parsedLink['host']) && $parsedLink['host'] != $this->domain) {
                //those are external links
            } else {
                if (isset($parsedLink['host']) && isset($parsedLink['path']) && $parsedLink['path'] != "/") {
                    $internalLinks[] = $parsedLink['host'].$parsedLink['path'];
                }
                if (substr($link, 0, 1) === "/" && $link != "/") {
                    $internalLinks[] = $this->domain.$link;
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
            if (isset($parsedLink['host']) && $parsedLink['host'] != $this->domain) {
                $domain                 = $parsedLink['host'];
                $externalLinks[$domain] = $domain;
            }
        }
        return array_unique(array_values($externalLinks));
    }

    public function saveContentToDisk($content, $name)
    {
        $filesystem = new Filesystem();
        $path       = storage_path('domains/'.$this->domain."/");
        $filesystem->dumpFile($path.$name, $content);
    }

    public function createUrlDatabase()
    {
        //first we check if an .sqlite database for this domain exists
        $path = storage_path('domains/'.$this->domain."/");

        $filesystem = new Filesystem();
        $filesystem->mkdir($path);

        $this->urlDatabase = new PDO('sqlite:'.$path.$this->domain.".sqlite");
        $this->urlDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->urlDatabase->exec("CREATE TABLE IF NOT EXISTS urls (
                    id INTEGER PRIMARY KEY,
                    url TEXT UNIQUE COLLATE nocase,
                    done INTEGER)");
    }
}
