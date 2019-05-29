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
        $this->whiteLitedTLD = ".hr";
        $this->domain        = $this->argument('domain');
        $this->createUrlDatabase();

        $counter = 0;
        $maxUrls = 500;

        $url = $this->domain;

        do {
            $status = $this->fetch($url);
            $url    = $this->getUrlFromUrlDatabase();
            $this->markUrlAsDone($url, $status);
            $counter++;
            //sleep(1);
        } while ($url || $counter < $maxUrls);
    }

    public function fetch($url)
    {
        $this->warn("Scraping $url");

        try {
            $response = $this->client->get($url);
            $body     = (string) $response->getBody();
        } catch (Exception $e) {
            //if we fail, we return with a error status code
            return 2;
        }

        $this->saveContentToDisk($body, $url);

        $links = $this->extractLinks($body);

        $externalLinks = $this->getExternalLinks($links);
        $internalLinks = $this->getInternalLinks($links);

        $externalLinksCount = count($externalLinks);
        $internalLinksCount = count($internalLinks);

        $this->info("\tFound {$internalLinksCount} internal links and {$externalLinksCount} external links");

        $this->saveExternalLinksToDomainFeeder($externalLinks);
        $this->saveInternalLinksToUrlDatabase($internalLinks);

        return 1;
    }

    public function saveInternalLinksToUrlDatabase($links)
    {
        $count = count($links);
        $this->info("\tSaving $count links to URL Database");

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

    public function markUrlAsDone($url, $done = 1)
    {
        $updateStmt = $this->urlDatabase->prepare("UPDATE urls SET done = :done WHERE url = :url");
        $updateStmt->bindValue(':done', $done);
        $updateStmt->bindValue(':url', $url);
        $updateStmt->execute();
    }

    public function getUrlFromUrlDatabase()
    {

        $selectStmt = $this->urlDatabase->prepare("SELECT * FROM urls WHERE done = 0");
        $selectStmt->execute();

        $url = $selectStmt->fetch(PDO::FETCH_ASSOC);

        if ($url) {
            return $url['url'];
        }

        return null;
    }

    public function saveExternalLinksToDomainFeeder($domains)
    {
        $count = count($domains);
        $this->info("\tSaving $count domains to Domain Feeder");
        foreach ($domains as $domain) {
            if (!$this->endsWith($domain, $this->whiteLitedTLD)) {
                continue;
            }
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

    public function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }
}
