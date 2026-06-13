<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

use RuntimeException;

/**
 * Raised for deploy-flow failures: the OAuth handshake, the loopback listener,
 * or a local precondition that should abort the command with a clear message.
 */
final class DeployException extends RuntimeException {}
