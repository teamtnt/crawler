<?php

namespace App\Console\Commands;

use App\DomainFeeder;
use DB;
use Illuminate\Console\Command;

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
    protected $description = 'Takes a domain from the domain feed';

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

        do {

            $domain = null;

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
            }
            sleep(1);
        } while (true);

    }
}
