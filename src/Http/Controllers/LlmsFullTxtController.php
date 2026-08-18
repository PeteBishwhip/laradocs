<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Response;
use Laradocs\Laradocs;

final class LlmsFullTxtController
{
    public function __construct(
        private readonly Laradocs $laradocs,
    ) {}

    public function __invoke(): Response
    {
        return new Response($this->laradocs->llmsFullTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
