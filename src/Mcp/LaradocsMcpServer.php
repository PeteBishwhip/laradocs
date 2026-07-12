<?php

declare(strict_types=1);

namespace Laradocs\Mcp;

use Composer\InstalledVersions;
use Laradocs\Mcp\Tools\FetchPageTool;
use Laradocs\Mcp\Tools\ListPagesTool;
use Laradocs\Mcp\Tools\SearchDocsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

class LaradocsMcpServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected $tools = [
        SearchDocsTool::class,
        ListPagesTool::class,
        FetchPageTool::class,
    ];
    protected function boot(): void
    {
        // petebishwhip/laradocs is always installed when this server runs, so
        // getVersion() resolves rather than throwing; keep the default as a
        // fallback for the (metapackage) null case.
        $this->version = InstalledVersions::getVersion('petebishwhip/laradocs') ?? $this->version;
    }
}
