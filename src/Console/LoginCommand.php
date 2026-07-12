<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Deploy\CredentialStore;
use Laradocs\Deploy\OAuthFlow;
use Laradocs\Support\Config;
use Throwable;

final class LoginCommand extends Command
{
    protected $signature = 'laradocs:login {--url= : Override the platform URL for this login}';

    protected $description = 'Authenticate the Laradocs CLI with your hosted platform';

    public function handle(OAuthFlow $oauth, CredentialStore $credentials): int
    {
        if ($url = $this->option('url')) {
            config(['laradocs.deploy.url' => $url]);
        }

        $base = Config::string('laradocs.deploy.url', 'https://laradocs.dev');

        $this->info("Opening your browser to authorize with {$base} …");

        try {
            $token = $oauth->login(function (string $authorizeUrl): void {
                $this->line('  If your browser did not open, visit:');
                $this->line('  ' . $authorizeUrl);
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $credentials->store($base, $token);

        $this->info('Authenticated. You can now run laradocs:deploy.');

        return self::SUCCESS;
    }
}
