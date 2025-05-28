<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\CurrencyRate;
class FetchCnyToUsdRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

protected $signature = 'currency:fetch-cny-usd';
    protected $description = 'Fetch the latest CNY to USD exchange rate and store it';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/cny.json';

        try {
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();
                $rate = $data['cny']['usd'] ?? null;
                $date = $data['date'] ?? now()->toDateString();

                if ($rate && $date) {
                    CurrencyRate::updateOrCreate(
                        ['date' => $date],
                        ['rate' => $rate]
                    );
                    $this->info("Saved rate: $rate for date: $date");
                } else {
                    $this->error("Missing 'usd' rate or date in response.");
                }
            } else {
                $this->error("Failed to fetch data: {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
    }
}
