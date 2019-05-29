<?php

namespace App\Console\Commands;

use App\DomainFeeder;
use DB;
use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Crawler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Takes domains from the domain feed and assignes each of them to a url frontier';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->warn("\nStarting crawler on node: ".env('NODE_NAME'));

        DB::connection('domain_feeder')->statement('PRAGMA journal_mode = wal;');

        $frontierIterator = 0;

        $frontiers                    = [];
        $maxNumberOfFrontierProcesses = 20;

        do {

            $domain = null;

            //checking if some of the url frontiers are complete
            foreach ($frontiers as $key => $frontier) {
                if (!$frontier->isRunning()) {
                    unset($frontiers[$key]);
                }
            }

            //if we already have maximum number of frontiers running we wait for them to complete
            if (count($frontiers) >= $maxNumberOfFrontierProcesses) {
                $this->info("Maximal number of frontiers are running, waiting for some to complete");
                sleep(3);
                continue;
            }

            DB::connection('domain_feeder')->transaction(function () use (&$domain) {
                $domain = DomainFeeder::whereNull('assigned_to')->first();
                if ($domain) {
                    $domain->assigned_on = now();
                    $domain->assigned_to = env('NODE_NAME');
                    $domain->save();
                } else {
                    $this->info("No available domains found");
                }
            }, 3);

            if ($domain) {
                $this->info("Taking {$domain->domain} from domain feed");

                $frontiers[$frontierIterator] = new Process(['php', 'artisan', 'url:frontier', $domain->domain]);
                $frontiers[$frontierIterator]->start();
                $frontierIterator++;
            }
            sleep(1);
        } while (true);

    }
}
