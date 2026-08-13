<?php

namespace App\Console\Commands;

use App\Services\Mpesa\DarajaClient;
use Illuminate\Console\Command;

/**
 * One-time setup: tell Safaricom where to send C2B callbacks.
 *
 * Until this has been run against the short code, the endpoints in this
 * application will never receive traffic no matter how correct they are.
 */
class RegisterMpesaUrls extends Command
{
    protected $signature = 'mpesa:register-urls
        {--confirmation= : Override the confirmation URL}
        {--validation= : Override the validation URL}
        {--status : Show the current configuration without registering}';

    protected $description = 'Register the C2B validation and confirmation URLs with Safaricom Daraja';

    public function handle(DarajaClient $daraja): int
    {
        $status = $daraja->status();

        $this->components->twoColumnDetail('Environment', $status['environment']);
        $this->components->twoColumnDetail('Daraja base URL', $status['base_url']);
        $this->components->twoColumnDetail('Short code', $status['shortcode'] ?? '<not set>');
        $this->components->twoColumnDetail('Confirmation URL', $this->option('confirmation') ?: $status['confirmation_url']);
        $this->components->twoColumnDetail('Validation URL', $this->option('validation') ?: $status['validation_url']);

        if ($this->option('status')) {
            if (! $status['configured']) {
                $this->components->warn('Missing config: '.implode(', ', $status['missing_config']));
            }

            return self::SUCCESS;
        }

        if (! $status['configured']) {
            $this->components->error(
                'Cannot register — missing config: '.implode(', ', $status['missing_config']).
                '. Set MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET and MPESA_SHORTCODE.'
            );

            return self::FAILURE;
        }

        // Registration is an outward-facing change to a live short code, so it
        // is confirmed rather than run silently.
        if (! $this->confirm("Register these URLs against short code {$status['shortcode']} on {$status['environment']}?", false)) {
            $this->components->info('Aborted. Nothing was sent to Safaricom.');

            return self::SUCCESS;
        }

        $result = $daraja->registerUrls(
            $this->option('confirmation') ?: null,
            $this->option('validation') ?: null,
        );

        if (! $result['success']) {
            $this->components->error('Registration failed: '.$result['error']);

            return self::FAILURE;
        }

        $this->components->info('C2B URLs registered.');
        $this->line(json_encode($result['data'], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
